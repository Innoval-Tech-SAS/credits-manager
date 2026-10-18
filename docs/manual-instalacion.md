# Manual de instalación

Instalación de **credits-manager** desde cero, en un equipo con Windows (o Linux) y Podman. Al final hay una aplicación funcionando, con su base de datos, su usuario administrador de plataforma y sus respaldos automáticos.

Todo corre en contenedores. **No hace falta instalar PHP, Node, Composer ni MySQL en el equipo.** La única herramienta que se instala en el equipo es `mkcert` (certificados HTTPS locales).

Para entender qué hace cada pieza, ver el [manual técnico](manual-tecnico.md). Para respaldos y restauración, ver [respaldos y restauración](respaldos.md).

---

## 0. Requisitos

| Requisito | Cómo comprobarlo |
|---|---|
| Podman (probado con la 6.0.2) y `podman compose`, que usa un proveedor de compose instalado (docker-compose o podman-compose) | `podman --version` y `podman compose version` |
| Podman en ejecución (en Windows: `podman machine start`) | `podman info` no muestra error |
| Git | `git --version` |
| mkcert | `mkcert -version` |
| Espacio libre | 15 GB como mínimo (imágenes, base y respaldos) |
| Puertos libres en el equipo | 80, 443 y 3306 (127.0.0.1) |

Si algún puerto está ocupado, detener el programa que lo usa antes de seguir.

---

## 1. Obtener el código

```bash
git clone https://github.com/Innoval-Tech-SAS/credits-manager.git
cd credits-manager
git checkout main
```

Comprobación: `git log -1 --oneline` muestra la última versión de `main`.

---

## 2. Dominios locales

La aplicación usa tres nombres. En el archivo de hosts de Windows (`C:\Windows\System32\drivers\etc\hosts`, abrir como administrador) añadir:

```
127.0.0.1  app.creditos.local
127.0.0.1  api.creditos.local
127.0.0.1  phpmyadmin.creditos.local
```

En Linux, el mismo bloque va en `/etc/hosts`.

> En un servidor de producción no se edita el archivo de hosts: los nombres se apuntan en el DNS y se cambian en `nginx/nginx.conf` (líneas `server_name`) y en los certificados (sección 3).

---

## 3. Certificados HTTPS

Instalar la autoridad local de mkcert una sola vez:

```bash
mkcert -install
```

Crear la carpeta `certs` y los certificados de los tres nombres. Los nombres de archivo deben coincidir con los de `nginx/nginx.conf`:

```bash
mkdir certs
mkcert -cert-file certs/app.creditos.local.pem        -key-file certs/app.creditos.local-key.pem        app.creditos.local
mkcert -cert-file certs/api.creditos.local.pem        -key-file certs/api.creditos.local-key.pem        api.creditos.local
mkcert -cert-file certs/phpmyadmin.creditos.local.pem -key-file certs/phpmyadmin.creditos.local-key.pem phpmyadmin.creditos.local
```

Comprobación: `ls certs` muestra seis archivos `.pem`.

---

## 4. Configuración: el archivo `.env`

Copiar la plantilla y editarla:

```bash
cp .env.example .env
```

### 4.1. Generar los secretos

Los secretos se generan dentro de un contenedor, sin herramientas en el equipo. Ejecutar cada comando y **copiar la salida** en la variable indicada:

```bash
# APP_KEY (clave de cifrado de la aplicación; guardarla con cuidado, ver sección 8 de docs/respaldos.md)
podman run --rm docker.io/library/php:8.4-fpm-alpine php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Contraseñas: cada comando devuelve un valor distinto; usar uno para cada variable
podman run --rm docker.io/library/php:8.4-fpm-alpine php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"

# Secretos de Reverb: REVERB_APP_KEY y REVERB_APP_SECRET (hex); REVERB_APP_ID puede ser cualquier número, p. ej. 100001
podman run --rm docker.io/library/php:8.4-fpm-alpine php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"
```

### 4.2. Variables que hay que llenar

Abrir `.env` y completar o revisar cada línea:

| Variable | Valor para esta instalación | Notas |
|---|---|---|
| `APP_KEY` | Salida de la primera orden de 4.1 | Obligatoria. Sin ella la aplicación no arranca. |
| `APP_URL` | `https://api.creditos.local` | En producción, la URL pública de la API. |
| `VITE_API_URL` | `https://api.creditos.local` | Se compila dentro de la interfaz; si cambia, reconstruir `nginx`. |
| `SESSION_DOMAIN` | `.creditos.local` | Dominio común de la sesión. |
| `SANCTUM_STATEFUL_DOMAINS` | `app.creditos.local` | Dominio de la interfaz. |
| `DB_DATABASE` | `creditos_db` | Nombre de la base. |
| `DB_USERNAME` | `creditos_app` | Usuario de la aplicación. **No puede ser `root`.** Lo crea MySQL en la primera instalación. |
| `DB_PASSWORD` | Una contraseña de 4.1 | Contraseña del usuario de la aplicación. |
| `DB_ROOT_PASSWORD` | Otra contraseña de 4.1 | Solo para administrar la base. |
| `SEED_ADMIN_USUARIO` | `admin` | Usuario del **administrador de la plataforma** (superadmin). |
| `SEED_ADMIN_PASSWORD` | Una contraseña fuerte | Se usa para el primer ingreso. Cambiarla después. |
| `SEED_ADMIN_NOMBRE` | `Administrador` | Nombre visible del superadmin. |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Valores de 4.1 | Notificaciones en tiempo real. |
| `VITE_REVERB_HOST` | `api.creditos.local` | Host del WebSocket. |
| `REVERB_ALLOWED_ORIGINS` | `app.creditos.local` | Origen permitido para el WebSocket. |
| `RESPALDOS_ORIGEN` | Vacío (o una carpeta, ver abajo) | Vacío: los respaldos van al volumen `respaldos_data`. Una ruta (p. ej. `/srv/respaldos-dda`) los deja en una carpeta que DDA puede copiar. |

La copia externa opcional a S3 no se configura aquí: el superadmin la activa después, desde la pantalla de **Respaldos** de la aplicación (ver [respaldos](respaldos.md), sección 2.1).

Para un servidor de producción, cambiar además `APP_URL`, `VITE_API_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `VITE_REVERB_HOST`, `REVERB_ALLOWED_ORIGINS` y `APP_ENV=production` (ya viene así), y los `server_name` de `nginx/nginx.conf`.

> `.env` no se sube al repositorio (está en `.gitignore`). Guardar una copia fuera del equipo: sin `APP_KEY` no se pueden leer los valores cifrados de un respaldo.

---

## 5. Construir las imágenes

```bash
podman compose build
```

La primera vez tarda varios minutos: descarga las imágenes base, instala las dependencias PHP y compila la interfaz. Termina con el mensaje de cada servicio construido y sin errores.

---

## 6. Levantar los servicios

```bash
podman compose up -d
```

Esperar a que la base y la API estén sanas (suele tardar menos de un minuto):

```bash
podman compose ps
```

En la columna `STATUS`, `database` y `api` deben decir `(healthy)`. Si alguno dice `starting` después de dos minutos, revisar `podman compose logs api`.

---

## 7. Primeras migraciones y datos iniciales

```bash
podman compose exec api php artisan migrate --seed
```

- `migrate` crea todas las tablas, índices, claves y restricciones (incluida la bitácora de solo inserción).
- `--seed` carga el catálogo de permisos, el catálogo geográfico de Colombia y el **superadmin** con `SEED_ADMIN_USUARIO` y `SEED_ADMIN_PASSWORD`.

Comprobar que la base es la del usuario de la aplicación y no `root`:

```bash
podman compose exec database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT CURRENT_USER();" "$MYSQL_DATABASE"'
```

Debe mostrar `creditos_app@%` (o el valor de `DB_USERNAME`).

---

## 8. Primer ingreso y creación del administrador de empresa

1. Abrir `https://app.creditos.local` e ingresar con `SEED_ADMIN_USUARIO` y `SEED_ADMIN_PASSWORD`. Este usuario es el **superadmin de la plataforma**: administra empresas y no pertenece a ninguna.
2. En el panel de **Empresas**, crear la primera empresa. Al crearla se pide también su **primer administrador** (nombre, usuario y contraseña). Ese es el usuario administrador de la empresa; con él se gestionan clientes, créditos, usuarios y permisos.
3. Cerrar sesión, ingresar con el administrador de la empresa y cambiar la contraseña desde el perfil.

Los roles base (administrador, asesor, cartera, contable y auditoría) se crean solos al crear la empresa.

---

## 9. Verificación de la instalación

Ejecutar cada comprobación. Todas deben salir como se indica.

| # | Comprobación | Comando o acción | Resultado esperado |
|---|---|---|---|
| 1 | Servicios sanos | `podman compose ps` | `database`, `api` con `(healthy)`; el resto `Up` |
| 2 | API responde | `curl -k https://api.creditos.local/up` | Código de respuesta 200 |
| 3 | Interfaz responde | Abrir `https://app.creditos.local` | Pantalla de ingreso, sin errores de certificado |
| 4 | Usuario de la base | Comando de la sección 7 | `creditos_app@%` |
| 5 | Tablas creadas | `podman compose exec database sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = \"$MYSQL_DATABASE\";"'` | 80 (una base recién migrada tiene 80 tablas) |
| 6 | Programación | `podman compose exec api php artisan schedule:list` | Incluye `respaldos:ejecutar` a las 01:00 |
| 7 | Respaldo manual | `podman compose exec --user www-data api php artisan respaldos:ejecutar --manual` | Mensaje `Respaldo manual creado en /var/respaldos/manual/…` |
| 8 | Sumas del respaldo | `podman compose exec --user www-data api sh -c 'cd "$(ls -d /var/respaldos/manual/* | tail -1)" && sha256sum -c SHA256SUMS'` | `archivos.tar.gz: OK` y `base_datos.sql.gz: OK` |

Si la comprobación 2 responde error de certificado, es que `mkcert -install` no se ejecutó en este equipo (sección 3). Con `curl -k` se omite la validación y la prueba sigue siendo válida.

---

## 10. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| `api` queda en `starting` o se reinicia | `APP_KEY` vacía o mal copiada | Revisar `.env`; `podman compose up -d api` |
| La interfaz responde **502** | `nginx` guardó la IP vieja de `api` tras recrearla | `podman compose restart nginx` |
| `migrate` dice «Access denied» | `DB_USERNAME`/`DB_PASSWORD` no coinciden con la base ya creada | La base solo crea el usuario en una base vacía; ver nota siguiente |
| `database` no crea `creditos_app` | El volumen `mysql_data` ya existía con datos | Normal en instalaciones previas; el usuario existe. No borrar el volumen para «arreglarlo» |
| Puerto 80, 443 o 3306 en uso | Otro programa lo ocupa | Detener el programa; no cambiar puertos sin revisar el compose |
| `respaldos:ejecutar` falla con «Permission denied» | La carpeta de `RESPALDOS_ORIGEN` no es escribible por `www-data` | Sección 2 de `docs/respaldos.md` |

**Nota para restauraciones.** Si la base se va a restaurar desde un respaldo de otra instalación, `DB_USERNAME` debe ser el mismo nombre que tenía esa instalación (ver [respaldos](respaldos.md), sección 7.2).

**Nota sobre el usuario de la base.** MySQL crea `MYSQL_USER` y `MYSQL_PASSWORD` solo cuando el volumen `mysql_data` está vacío (primer arranque). En una instalación que ya tiene datos, el usuario debe existir de antemano con los mismos valores de `.env`.

---

## 11. Después de instalar

- Hacer un respaldo manual y verificarlo (comprobación 7 y 8).
- Copiar `.env` a un lugar seguro fuera del equipo.
- Revisar que la carpeta de respaldos (`RESPALDOS_ORIGEN` o el volumen `respaldos_data`) está en un disco distinto al de la base, y que DDA sabe cómo copiarla ([docs/respaldos.md](respaldos.md), sección 6).
- Para actualizar la aplicación, seguir el procedimiento de despliegue del [manual técnico](manual-tecnico.md#procedimiento-de-despliegue).
