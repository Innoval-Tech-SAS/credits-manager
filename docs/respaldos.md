# Respaldos y restauración

Qué se respalda, dónde queda, con qué frecuencia, cómo copiarlo sin la aplicación y cómo restaurarlo. Lo usan DDA (para copiar y custodiar) y el equipo técnico (para operar y restaurar).

## 1. Qué contiene cada respaldo

| Parte | Contenido | Formato |
|---|---|---|
| Base de datos | Todas las tablas de la base de la aplicación (todas las empresas), con triggers, rutinas y eventos. Sin `sessions`, `cache` ni `cache_locks`, que se regeneran solas. | `base_datos.sql.gz`: texto SQL comprimido con gzip. Se abre con cualquier cliente MySQL o MariaDB. |
| Archivos | Carpeta `app/` (`storage/app`: adjuntos de clientes y documentos, logos de empresa), `exportaciones/` e `importaciones/` (archivos subidos y generados). | `archivos.tar.gz`: tar comprimido con gzip. Se abre con 7-Zip, WinRAR o `tar -xzf`. |
| Verificación | Sumas SHA-256 de los dos archivos anteriores. | `SHA256SUMS`: formato de `sha256sum`. |
| Ficha | Fecha de creación, clase, zona horaria, nombre de la base y contenido. **No** lleva usuario, contraseña ni host de la base. | `respaldo.json`: texto plano. |

**No se respalda** el archivo `.env` ni ningún secreto de configuración. Ver la sección 8 (custodia de `APP_KEY`).

## 2. Dónde queda

Por defecto, en el volumen dedicado `respaldos_data`, montado en `/var/respaldos` dentro de los contenedores `api` y `scheduler`.

Para que DDA pueda copiarlo con sus herramientas, asignar en `.env` una carpeta del equipo anfitrión:

```
RESPALDOS_ORIGEN=/srv/respaldos-dda
```

La carpeta debe existir y ser escribible por el usuario `www-data` del contenedor (uid 82 en la imagen Alpine). Comprobación después de cambiarla:

```bash
podman compose up -d
podman compose exec --user www-data api sh -c 'touch /var/respaldos/.prueba && rm /var/respaldos/.prueba && echo OK'
```

En Podman sin root hay que ajustar el dueño de la carpeta en el host, por ejemplo `podman unshare chown 82:82 /srv/respaldos-dda`.

Estructura:

```
/var/respaldos/
├── diario/      2026-10-07_010000/  base_datos.sql.gz  archivos.tar.gz  SHA256SUMS  respaldo.json
├── semanal/     2026-10-04_010000/  ...
├── mensual/     2026-11-01_010000/  ...
└── manual/      2026-10-07_153000/  ...
```

Cada carpeta lleva la fecha y hora de creación en su nombre (`AAAA-MM-DD_HHMMSS`, hora de Bogotá).

## 3. Frecuencia y retención

| Clase | Cuándo se crea | Se conserva | Dónde |
|---|---|---|---|
| Diaria | Todos los días a las 01:00 | 7 días | `diario/` |
| Semanal | Los domingos, a las 01:00 (copia del diario) | 1 mes (31 días) | `semanal/` |
| Mensual | El día 1 de cada mes, a las 01:00 (copia del diario) | 1 año (365 días) | `mensual/` |
| Manual | Cuando se ejecuta el comando antes de una operación crítica | 1 año (365 días) | `manual/` |

- La antigüedad se mide por la fecha del nombre de la carpeta, no por la fecha de modificación.
- La retención corre solo después de un respaldo correcto; un fallo no borra los anteriores.
- Un respaldo incompleto no aparece: se escribe en una carpeta temporal que empieza por punto (`.tmp-…`) y se renombra solo al terminar. Si falla, la carpeta temporal se borra.
- El respaldo automático lo ejecuta el servicio `scheduler` (`routes/console.php`, tarea `respaldos:ejecutar`). Si falla, queda en `podman compose logs scheduler`.

Con la retención indicada, el espacio ocupado es aproximadamente: 7 diarios + 5 semanales + 12 mensuales + los manuales del último año.

## 4. Respaldo manual

Antes de una **operación crítica**, en especial una migración y el cierre de un período contable, se ejecuta:

```bash
podman compose exec --user www-data api php artisan respaldos:ejecutar --manual
```

La salida indica la carpeta creada. Si el comando termina con error, **no** seguir con la operación.

El respaldo manual no sustituye al diario: se guarda en `manual/` y tiene un año de retención. El cierre de período se hace desde la aplicación; el respaldo se toma antes, desde la consola, como paso del procedimiento.

## 5. Verificar un respaldo

Desde cualquier equipo, en la carpeta del respaldo:

```bash
sha256sum -c SHA256SUMS
```

En Windows (PowerShell), para comparar cada archivo con su suma:

```powershell
Get-FileHash -Algorithm SHA256 .\base_datos.sql.gz
Get-FileHash -Algorithm SHA256 .\archivos.tar.gz
```

Cada hash debe coincidir con la línea correspondiente de `SHA256SUMS`. Si no coincide, el archivo está dañado o fue alterado: no restaurarlo.

## 6. Copia por DDA, sin depender de la aplicación

**Si `RESPALDOS_ORIGEN` apunta a una carpeta del equipo:** basta con copiar esa carpeta (Explorador de Windows, `robocopy`, `rsync`, o la herramienta de copia de DDA). Después, verificar la copia con la sección 5.

```powershell
robocopy D:\respaldos-dda \\servidor-dda\respaldos /E /Z /R:3 /W:5 /LOG:C:\respaldos-copia.log
```

**Si el respaldo está en el volumen `respaldos_data`:** copiarlo con un contenedor auxiliar, sin tocar la aplicación. Ejemplo con Podman, copiando a `D:\respaldos-dda`:

```bash
podman run --rm -v credits-manager_respaldos_data:/origen:ro -v D:/respaldos-dda:/destino docker.io/library/alpine cp -a /origen/. /destino/
```

El nombre del volumen es `<carpeta-del-proyecto>_respaldos_data`; comprobarlo con `podman volume ls`.

La copia es útil solo si se verifica: repetir la sección 5 sobre la copia, no sobre el original.

## 7. Restauración

> **No restaurar sobre la base en uso sin confirmación expresa de DDA.** Antes de cualquier restauración sobre una instalación con datos, tomar un respaldo manual (sección 4).

### 7.1. Preparar

1. Elegir la carpeta del respaldo (diario, semanal, mensual o manual) y verificar sus sumas (sección 5).
2. Extraer los archivos en una carpeta de trabajo, separada de la instalación:

   ```bash
   mkdir -p /srv/restauracion
   tar -xzf archivos.tar.gz -C /srv/restauracion
   ```

   Queda `/srv/restauracion/app/`, `…/exportaciones/` e `…/importaciones/`.

### 7.2. Base de datos

**El usuario de la aplicación debe tener el mismo nombre que en la instalación de origen.** El volcado guarda como definidor de los triggers (incluida la bitácora de solo inserción) al usuario con el que se creó: si no existe en la instalación destino, cada escritura en la bitácora falla con `ERROR 1449 … definer does not exist`. Para ver el nombre antes de restaurar:

```bash
zcat base_datos.sql.gz | grep -o "DEFINER=\`[^\`]*\`@\`[^\`]*\`" | sort -u
```

Crear en el destino el usuario con ese nombre (variable `DB_USERNAME` del `.env` del destino) y restaurar como `root`, porque el volcado incluye definidores de triggers y rutinas:

```bash
gunzip -c base_datos.sql.gz | podman exec -i credits-manager-database sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

La contraseña de `root` se lee del propio contenedor; no se escribe en la línea de comandos ni en un archivo.

### 7.3. Archivos

Copiar cada carpeta al volumen correspondiente (los nombres de volumen son `<proyecto>_api_storage`, `<proyecto>_exportaciones_data` e `<proyecto>_importaciones_data`):

```bash
podman run --rm -v credits-manager_api_storage:/destino -v /srv/restauracion/app:/origen:ro docker.io/library/alpine sh -c 'mkdir -p /destino/app && cp -a /origen/. /destino/app/'
podman run --rm -v credits-manager_exportaciones_data:/destino -v /srv/restauracion/exportaciones:/origen:ro docker.io/library/alpine cp -a /origen/. /destino/
podman run --rm -v credits-manager_importaciones_data:/destino -v /srv/restauracion/importaciones:/origen:ro docker.io/library/alpine cp -a /origen/. /destino/
```

### 7.4. Arranque y comprobación

```bash
podman compose up -d
```

Comprobar: `https://api.<dominio>/up` responde 200; ingresar a la aplicación; revisar que los conteos de tablas y filas corresponden al respaldo (sección 9).

### 7.5. Clave de cifrado

Algunos valores (por ejemplo, la clave del correo de cobranza) están cifrados con `APP_KEY`. Restaurar **requiere la misma `APP_KEY`** que tenía la instalación de origen. Sin ella, esos valores no se pueden leer y hay que volver a capturarlos. Ver sección 8.

## 8. Custodia de secretos

- Los respaldos **no** contienen `.env`, ni contraseñas, ni la clave `APP_KEY`.
- DDA debe custodiar, fuera del servidor y junto a los respaldos, una copia del `.env` de producción (o al menos `APP_KEY`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` y `REVERB_*`). Sin `APP_KEY` el respaldo sigue siendo legible en lo demás, pero los valores cifrados no.
- Los respaldos contienen **datos personales y financieros** (clientes, créditos, usuarios con sus hashes de contraseña). Su custodia debe tener el mismo nivel que la base en producción: carpeta con acceso restringido y copia cifrada si el medio sale de la oficina.
- El volcado omite las credenciales del motor: la contraseña viaja solo en el entorno del proceso y nunca en los argumentos, la ficha ni los registros.

## 9. Evidencia de la última prueba de restauración

Ejecutada el 2026-10-05 sobre el respaldo manual `manual/2026-10-05_230100` de la instalación viva.

| Comprobación | Origen (base viva) | Restaurado en MySQL 8.4 limpio | Restaurado en instalación limpia del manual |
|---|---|---|---|
| Sumas `SHA256SUMS` | OK | OK (copia por el procedimiento de la sección 6) | OK |
| Tablas | 61 | 58 (`sessions`, `cache`, `cache_locks` excluidas) | 61 |
| Filas, tabla por tabla | — | Iguales en todas las tablas incluidas | Iguales en todas las tablas incluidas (`cache` y `sessions` conservan filas de la instalación nueva) |
| Triggers | 8 | 8 | 8 |
| Claves foráneas | 152 | 152 | — |
| Índices | 247 | 242 (los 5 de las tablas excluidas) | — |
| Bitácora de solo inserción | — | `UPDATE` y `DELETE` rechazados (45000) | `UPDATE` rechazado (45000) |
| Archivos `app/`, `exportaciones/`, `importaciones/` | — | 5, 0 y 4 archivos con las mismas sumas que el volumen vivo | 5, 0 y 4 archivos con las mismas sumas que el volumen vivo |

Hallazgos del procedimiento:

- **Usuario de la aplicación:** el volcado guarda el definidor de los triggers (`admin-dev`@`%`). Sin ese usuario en el destino, cada escritura en la bitácora falla con `ERROR 1449` (primera prueba, con otro nombre de usuario). Por eso la sección 7.2 exige el mismo nombre.
- **Esquema:** la huella del esquema no coincide con la de origen. La diferencia observada es solo de formato: el volcado de MariaDB escribe `COLLATE utf8mb4_unicode_ci` sin repetir `CHARACTER SET`, y MySQL 8.4 lo muestra con ambas cláusulas. La codificación y la collation son las mismas, pero no se comprobó byte a byte contra el esquema de origen, porque la instalación viva estaba detenida durante la comparación.
- **Muestra de archivos:** pequeña (9 archivos en total). La restauración de archivos está probada en mecánica, no en volumen.
- **Instalación desde cero:** se siguió el manual sobre una copia del árbol de trabajo (la rama no está commiteada). Se usó `DB_USERNAME=admin-dev`, el mismo nombre de la instalación de origen, en lugar del ejemplo `creditos_app`. Se encontró y corrigió un error del manual (conteo de tablas esperado).

## 10. Responsables

- Responsable de la ejecución y de la verificación de respaldos: *pendiente de designar por DDA y el contratista.*
- Canal para entrega de respaldos (anexo técnico, «Canal para entrega de repositorio y respaldos»): *pendiente.*
