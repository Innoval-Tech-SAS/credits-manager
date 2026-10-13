# CI/CD — credits-manager

## 1. Cómo funciona

```
PR hacia main ──► CI (.github/workflows/ci.yml)
                    ├─ API: Pint + PHPUnit (con Redis real)
                    ├─ Migraciones: todas, desde cero, en MySQL 8.4
                    ├─ Cliente: lint + Vitest + build
                    └─ Imágenes de producción: api y nginx construyen
                  Branch protection: sin los 4 checks en verde no se puede hacer merge.

merge a main ──► CI otra vez sobre el commit de main
             ──► Deploy (.github/workflows/deploy.yml)
                    ├─ environment `PROD` (secretos de despliegue, solo rama main)
                    └─ SSH al servidor ──► scripts/deploy.sh <sha>    (automático, sin aprobación)
```

`scripts/deploy.sh` (instalado como `/usr/local/sbin/credits-manager-deploy`) hace el procedimiento de §5 del [manual técnico](manual-tecnico.md):

1. Valida que el SHA sea el de `origin/main`. Si hubo un merge posterior, no hace nada: ese otro despliegue se encarga.
2. Hace `git merge --ff-only` en el checkout de producción.
3. **Respaldo previo** (`respaldos:ejecutar --manual`). Si falla, se detiene sin tocar los contenedores.
4. Ejecuta `podman compose build` y etiqueta las imágenes con el SHA para poder revertir.
5. Si el cambio trae migraciones, pone la aplicación en `artisan down`.
6. Recrea solo los servicios de la aplicación (`--force-recreate --no-deps`). La base, Redis y phpMyAdmin no se reinician.
7. Ejecuta `migrate --force`, luego `artisan up` y `restart nginx`.
8. Comprueba que `https://api…/up` y `https://app…/` respondan 200.
9. Guarda el SHA desplegado en `/var/lib/credits-manager/desplegado` y conserva las imágenes de los últimos 5 despliegues.

Si una migración falla, la aplicación **queda en mantenimiento a propósito**: una base a medio migrar no debe atender usuarios. El log del workflow indica cómo seguir.

> `podman-compose` no recrea un contenedor con `up -d` si solo cambió la imagen; por eso el script usa `--force-recreate`. Lo mismo aplica a un despliegue manual.

**Autorización de migraciones:** el despliegue es totalmente automático (decisión del 2026-10-13: en repositorios privados GitHub solo ofrece aprobación manual de environments con el plan Enterprise). **El merge a `main` es la autorización**: quien aprueba el PR debe revisar si trae migraciones (`api/database/migrations/`) y si son compatibles hacia atrás (§3.1).

---

## 2. Configuración inicial (una sola vez)

### 2.1. Servidor: usuario de despliegue

Como root en `62.72.21.110`:

```bash
# Usuario sin contraseña (useradd la deja bloqueada), solo para recibir el SSH de GitHub
useradd --create-home --shell /bin/bash deploy

# Instalar el script como copia de root (el usuario deploy no puede modificarlo)
install -o root -g root -m 0755 /root/repositories/credits-manager/scripts/deploy.sh \
  /usr/local/sbin/credits-manager-deploy

# Permitir a deploy ejecutar SOLO ese script como root
echo 'deploy ALL=(root) NOPASSWD: /usr/local/sbin/credits-manager-deploy' > /etc/sudoers.d/credits-manager-deploy
chmod 440 /etc/sudoers.d/credits-manager-deploy
visudo -c
```

Cada vez que `scripts/deploy.sh` cambie en el repositorio, hay que repetir el `install`. El script avisa al final de cada despliegue si la copia instalada quedó desactualizada.

### 2.2. Llave SSH de GitHub

En el servidor, como root:

```bash
ssh-keygen -t ed25519 -N '' -C 'github-actions-deploy' -f /root/deploy_key

install -o deploy -g deploy -m 700 -d /home/deploy/.ssh
printf 'restrict,command="sudo -n /usr/local/sbin/credits-manager-deploy \\"$SSH_ORIGINAL_COMMAND\\"" %s\n' \
  "$(cat /root/deploy_key.pub)" > /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
restorecon -R /home/deploy/.ssh       # solo si `getenforce` no dice Disabled (hoy está Disabled)

# Huella del servidor para el known_hosts de GitHub
ssh-keyscan -t ed25519 62.72.21.110
```

Con `restrict,command=…` esa llave **solo** puede ejecutar el script de despliegue: no abre shell ni permite túneles. El script descarta cualquier argumento que no sea un SHA de 40 caracteres.

Copia el contenido de `/root/deploy_key` (llave privada) para el paso siguiente y **después bórrala del servidor**: `shred -u /root/deploy_key`.

Prueba desde el servidor (debe responder "se esperaba el SHA completo…", y eso confirma que el `command=` forzado funciona):

```bash
ssh -i /root/deploy_key deploy@127.0.0.1 hola
```

El puerto SSH (22) debe aceptar conexiones desde internet, porque los runners de GitHub no tienen IP fija.

### 2.2.1. Servidor: lectura del repositorio sin contraseña

El script hace `git fetch` como root sin nadie que escriba un token, así que el servidor necesita su propia **deploy key** de solo lectura:

```bash
ssh-keygen -t ed25519 -N '' -C 'servidor-produccion-lectura' -f /root/.ssh/github_credits_manager
cat >> /root/.ssh/config <<'EOF'
Host github.com
  IdentityFile /root/.ssh/github_credits_manager
  IdentitiesOnly yes
EOF
chmod 600 /root/.ssh/config
ssh-keyscan -t ed25519 github.com >> /root/.ssh/known_hosts
cat /root/.ssh/github_credits_manager.pub
```

En GitHub: **Settings → Deploy keys → Add deploy key**, título `servidor produccion (solo lectura)`, pega la llave pública y **deja sin marcar** *Allow write access*. Luego:

```bash
ssh -T git@github.com     # "Hi Innoval-Tech-SAS/credits-manager! You've successfully authenticated…"
git -C /root/repositories/credits-manager remote set-url origin git@github.com:Innoval-Tech-SAS/credits-manager.git
git -C /root/repositories/credits-manager fetch origin
```

Con esto `origin` queda de solo lectura en el servidor. Para subir una rama desde aquí, usa la URL HTTPS con tu token: `git push https://github.com/Innoval-Tech-SAS/credits-manager.git <rama>`.

### 2.3. GitHub: environment y secretos

En `github.com/Innoval-Tech-SAS/credits-manager` → **Settings → Environments → New environment** → `PROD`:

- **Deployment branches and tags:** *Selected branches and tags* → `main`. Así ninguna rama de PR puede leer los secretos.
- **Environment secrets:**

| Secreto | Valor |
|---|---|
| `DEPLOY_HOST` | `62.72.21.110` |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_SSH_KEY` | contenido completo de `/root/deploy_key`, incluidas las líneas `-----BEGIN/END…-----` |
| `DEPLOY_KNOWN_HOSTS` | salida de `ssh-keyscan -t ed25519 62.72.21.110` |

**Plan de la organización:** en GitHub Free, los environments, sus secretos y los rulesets no se aplican a repositorios privados. Si el job de Deploy falla en *Verificar secretos*, mueve los 4 secretos a **Settings → Secrets and variables → Actions** (secretos del repositorio). Protegerlos solo para la rama `main` y hacer cumplir el ruleset requiere GitHub Team.

*Required reviewers* no aparece en repositorios privados fuera del plan Enterprise; por eso el despliegue es automático. Si algún día se quiere aprobación manual sin Enterprise, la alternativa es quitar el disparador `workflow_run` de `deploy.yml` y desplegar siempre con *Run workflow*.

### 2.4. GitHub: proteger `main`

**Settings → Branches → Add branch ruleset** (o *Rules → Rulesets*), con destino `main`:

- *Require a pull request before merging*.
- *Require status checks to pass* con estos checks (aparecen después de la primera ejecución del CI):
  `API (Pint + PHPUnit)`, `Migraciones en MySQL 8.4`, `Cliente (lint + pruebas + build)`, `Imágenes de producción`.
- *Block force pushes*.

### 2.5. Primer despliegue

El primer despliegue construye `localhost/credits-manager-nginx`, un nombre de imagen nuevo. Es normal que tarde más.

1. Haz merge del PR que trae este CI/CD.
2. Ve a **Actions → Deploy**. Cuando el CI de `main` termine, el despliegue arranca solo; sigue el log.
3. Si el CI de ese primer commit falla (por ejemplo, Pint encuentra estilo pendiente), corrígelo en otro PR. El despliegue solo corre con el CI en verde.

---

## 3. Operación

| Necesito… | Cómo |
|---|---|
| Desplegar | Merge a `main` (despliega solo cuando el CI pasa) |
| Re-desplegar `main` (por ejemplo, tras un fallo) | Actions → Deploy → *Run workflow* sobre `main` |
| Desplegar a mano desde el servidor | `sudo credits-manager-deploy "$(git -C /root/repositories/credits-manager rev-parse origin/main)"` tras un `git fetch` |
| Ver qué está desplegado | `cat /var/lib/credits-manager/desplegado` |

### 3.1. Revertir

**Sin migraciones de por medio**, la vía normal es un PR con `git revert` del commit: al hacer merge, el pipeline despliega la versión revertida.

Si no puedes esperar al CI, vuelve a la imagen anterior a mano (SHA en `/var/lib/credits-manager/historial`) y después haz igual el PR de revert, para que `main` coincida con lo desplegado:

```bash
cd /root/repositories/credits-manager
ANTERIOR=<sha>
podman tag localhost/credits-manager-api:$ANTERIOR   localhost/credits-manager-api:latest
podman tag localhost/credits-manager-nginx:$ANTERIOR localhost/credits-manager-nginx:latest
podman compose up -d --force-recreate --no-deps \
  auditoria-listener exportaciones-worker importaciones-worker conciliacion-worker \
  correos-worker scheduler reverb api nginx
echo $ANTERIOR > /var/lib/credits-manager/desplegado
```

**Con migraciones:** `migrate:rollback` está prohibido. La vía es restaurar el respaldo previo que tomó el despliegue ([respaldos](respaldos.md) §7) y luego volver a la imagen anterior como arriba. Por eso las migraciones deben ser compatibles hacia atrás: primero se agrega (columna nueva, nullable) y en un despliegue posterior se quita lo viejo.

### 3.2. Efectos del despliegue en curso

- Los workers reciben `SIGTERM`. Un job que esté corriendo (una exportación larga, por ejemplo) se corta y queda fallido (`--tries=1`), así que el usuario debe volver a lanzarlo. Conviene desplegar fuera de horas de uso.
- Si el despliegue coincide con las tareas de la madrugada (00:05–03:15), la tarea se reintenta al día siguiente. Mejor no hacer merge a `main` en esa franja: cada merge despliega.
