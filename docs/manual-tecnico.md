# Manual técnico

Arquitectura, configuración, operación y despliegue de credits-manager. Para instalar desde cero, ver el [manual de instalación](manual-instalacion.md). Para respaldos, ver [respaldos y restauración](respaldos.md).

Público: quien opera o mantiene el sistema.

---

## 1. Arquitectura

```
Navegador ──HTTPS──▶ nginx ──┬── /            SPA React (build estático)
                             ├── /api/…       PHP-FPM (api) ──▶ MySQL 8.4 (database)
                             │                              └──▶ Redis 7 (redis)
                             └── /app (WS)    Reverb (reverb)
```

| Servicio | Imagen | Rol |
|---|---|---|
| `database` | `docker.io/library/mysql:8.4` | Base de datos. Zona horaria `America/Bogota`. Crea el usuario de la aplicación en la primera instalación. |
| `redis` | `docker.io/library/redis:7-alpine` | Bitácora de auditoría (Redis Streams) y cola de exportaciones. |
| `phpmyadmin` | `docker.io/phpmyadmin/phpmyadmin:latest` | Administración de la base, solo para técnicos. |
| `api` | `credits-manager-api` (construida desde `api/`) | PHP-FPM con Laravel 13. Atiende las peticiones de la SPA. |
| `auditoria-listener` | misma imagen | Lee la bitácora de Redis e inserta en la base (idempotente). |
| `horizon` | misma imagen | Laravel Horizon: un supervisor por cola (`exportaciones`, `importaciones`, `conciliacion`, `correos`), cada uno con su propio timeout/tries en `api/config/horizon.php` — un job lento de una cola no bloquea a las demás. Panel en `/horizon`, solo superadmin. |
| `scheduler` | misma imagen | Tareas programadas de Laravel. **Una sola instancia.** |
| `monitor` | misma imagen | Panel de salud del superadmin (`/monitoreo`): cada 5 s, y solo si alguien lo mira, publica por Reverb el estado del servidor (leído de `/proc`), los servicios y las colas. Monta `respaldos_data` en solo lectura para medir el disco. |
| `reverb` | misma imagen | Servidor WebSocket para notificaciones en tiempo real. |
| `nginx` | construida desde `nginx/` | Sirve la SPA y hace de proxy a `api` y `reverb`. Termina HTTPS. |

### 1.1. Volúmenes

| Volumen | Montado en | Contenido | Quién escribe |
|---|---|---|---|
| `mysql_data` | `database:/var/lib/mysql` | Base de datos | MySQL |
| `redis_data` | `redis:/data` | Persistencia de Redis (AOF) | Redis |
| `api_storage` | `api`, `scheduler`, `horizon`: `/var/www/html/storage`; `nginx` en solo lectura | Adjuntos (`storage/app/private`), logos (`storage/app/public`), logs y caché | `api`, `horizon` |
| `exportaciones_data` | `/var/exportaciones` | Archivos de exportación (se limpian a los 7 días) | `horizon` |
| `importaciones_data` | `/var/importaciones` | CSV subidos para importación | `api`, `horizon` |
| `conciliacion_data` | `api`, `horizon`, `scheduler`: `/var/conciliacion` | Extractos bancarios subidos para la conciliación | `api`, `horizon` |
| `respaldos_data` | `api`, `scheduler`: `/var/respaldos` | Respaldos de base y archivos | `scheduler`, `api` (manual) |

`respaldos_data` puede sustituirse por una carpeta del equipo con `RESPALDOS_ORIGEN` en `.env` (ver [respaldos](respaldos.md), sección 2).

> `nginx` no monta `api_storage` con escritura. Los archivos se descargan siempre por endpoints autenticados.

---

## 2. Variables de entorno

Se leen de `.env` (raíz del repositorio). La plantilla comentada es `.env.example`. `horizon`, el scheduler y `reverb` comparten el mismo bloque de variables (ancla `api-worker-env` en el compose).

| Variable | Usada por | Uso |
|---|---|---|
| `APP_NAME`, `APP_ENV`, `APP_DEBUG` | api y horizon | Nombre; en producción `APP_ENV=production` y `APP_DEBUG=false`. |
| `APP_KEY` | api y horizon | Clave de cifrado (valores cifrados como la clave SMTP). **Custodia obligatoria.** |
| `APP_URL`, `APP_TIMEZONE`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | api y horizon | URL pública; zona horaria (`America/Bogota`); idioma (`es`). |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | database (creación), api y horizon | Base y usuario de la aplicación. `DB_USERNAME` no puede ser `root`. |
| `DB_ROOT_PASSWORD` | database | Contraseña de `root` de MySQL. No la usa la aplicación. |
| `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` | api | Cookies de sesión de la SPA (también protege `/horizon`, solo superadmin). |
| `SEED_ADMIN_NOMBRE`, `SEED_ADMIN_USUARIO`, `SEED_ADMIN_PASSWORD` | api (seed) | Superadmin inicial. Solo se usa al ejecutar `migrate --seed`. |
| `REDIS_HOST`, `REDIS_PORT` | api y horizon | Redis. |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | api, reverb | Credenciales del WebSocket. |
| `REVERB_ALLOWED_ORIGINS` | api, reverb | Orígenes permitidos para el WebSocket. |
| `VITE_API_URL`, `VITE_REVERB_*` | build de `nginx` | Se compilan dentro de la interfaz; cambiarlas exige reconstruir `nginx`. |
| `RESPALDOS_ORIGEN` | compose (montaje) | Carpeta del host para `/var/respaldos`. Vacío = volumen `respaldos_data`. |
| `RESPALDOS_RUTA` | api (config) | Ruta dentro del contenedor. Por defecto `/var/respaldos`. |
| `RESPALDOS_BINARIO_VOLCADO` | api (config) | Cliente de volcado. Por defecto `mariadb-dump`. |

Variables de configuración por archivo (no `.env`): `api/config/respaldos.php` define las tablas excluidas del respaldo y los directorios de archivos.

---

## 3. Tareas programadas

Las ejecuta el servicio `scheduler` (`schedule:work`), en hora de Bogotá. Definidas en `api/routes/console.php`.

| Hora | Tarea | Qué hace |
|---|---|---|
| 00:05 | `cuotas:actualizar-vencidas` | Marca la mora de todas las empresas. |
| 01:00 | `respaldos:ejecutar` | Respaldo diario de base y archivos (y copia semanal/mensual cuando corresponde). |
| 03:00 | `exportaciones:limpiar` | Borra exportaciones vencidas. |
| 03:15 | `importaciones:limpiar` | Borra importaciones vencidas. |
| 23:55 | `metas:evaluar` | Evalúa las metas de cada empresa, cierra los períodos vencidos y genera alertas. |
| 06:00 | `obligaciones_tributarias:evaluar-alertas` | Genera las alertas del calendario tributario por vencer de cada empresa. |

La hora es la por defecto de arriba hasta que el superadmin la cambie desde el panel **Tareas programadas** (`/monitoreo`, endpoints `GET/PATCH /api/v1/tareas-programadas`): el cambio se lee de la tabla `tareas_programadas` y aplica en menos de un minuto, sin reiniciar nada. Todas las tareas se pueden pausar desde ahí salvo `respaldos:ejecutar`, que nunca se pausa.

Las tareas no se solapan (`withoutOverlapping`). Un fallo de `respaldos:ejecutar` deja un registro de error en `podman compose logs scheduler`.

Ver la programación: `podman compose exec api php artisan schedule:list`.

---

## 4. Respaldos

Resumen (detalle en [respaldos y restauración](respaldos.md)):

- Comando: `respaldos:ejecutar` (`api/src/Respaldos/`). Opción `--manual` para el respaldo previo a operaciones críticas.
- Módulo: `Commands/EjecutarRespaldoCommand` → `Services/RespaldoService` (orquesta), `VolcadoBaseDatosService` (base), `ArchivosRespaldoService` (archivos), `RetencionRespaldosService` (retención).
- La base se vuelca con `mariadb-dump` (cliente de MariaDB, incluido en la imagen `api` por el paquete `mysql-client` de Alpine). Se usa `--single-transaction` para una foto consistente de InnoDB sin bloquear la aplicación.
- La contraseña viaja en la variable de entorno `MYSQL_PWD` del proceso, nunca en los argumentos.
- Escritura atómica: carpeta temporal `.tmp-…` y renombrado al terminar.
- Copia externa opcional a un disco S3 (`SubidaRespaldoExternoService`): el superadmin la activa y configura desde la pantalla de **Respaldos** (`/respaldos`, no por `.env`); sin activar o sin credenciales no hace nada, solo avisa en el log. El resultado de la última subida se ve en el panel de salud del superadmin (`/monitoreo`).

---

## 5. Procedimiento de despliegue

El despliegue normal es automático: merge a `main` → CI → aprobación en GitHub → `scripts/deploy.sh` en el servidor. Ver [CI/CD](ci-cd.md). Los pasos de abajo son lo que hace el script y sirven como procedimiento manual de respaldo.

Aplicar cada versión de `main` con este orden. **El respaldo previo es obligatorio antes de tocar la base.**

```bash
# 1. Actualizar el código
git fetch origin && git checkout main && git pull --ff-only

# 2. Respaldo previo a la migración (ver docs/respaldos.md, sección 4)
podman compose exec --user www-data api php artisan respaldos:ejecutar --manual
#    Si el comando falla, detenerse: no seguir.

# 3. Reconstruir las imágenes cambiadas
podman compose build

# 4. Recrear los servicios de la aplicación (podman-compose no recrea con `up -d` si solo cambió la imagen)
podman compose up -d --force-recreate --no-deps \
  auditoria-listener horizon scheduler monitor reverb api nginx

# 5. Migrar la base
podman compose exec api php artisan migrate --force

# 6. Si se recreó api, reiniciar nginx (si no, responde 502 con la IP vieja de api)
podman compose restart nginx

# 7. Verificar
curl -k https://api.<dominio>/up                         # 200
podman compose exec api php artisan schedule:list        # incluye respaldos:ejecutar
```

Después del despliegue, revisar la interfaz en el navegador (ingreso y una pantalla de la versión).

**Operaciones críticas** que también requieren el respaldo previo, aunque no sean un despliegue: el cierre de un período contable y cualquier restauración de datos.

---

## 6. Operación habitual

| Tarea | Comando |
|---|---|
| Ver estado | `podman compose ps` |
| Ver logs de un servicio | `podman compose logs --tail 200 scheduler` |
| Reiniciar un servicio | `podman compose restart api` (y luego `nginx` si era `api`) |
| Respaldo manual | `podman compose exec --user www-data api php artisan respaldos:ejecutar --manual` |
| Limpiar caché de configuración | `podman compose exec api php artisan config:clear` |
| Consola de Laravel | `podman compose exec api php artisan tinker` |

---

## 7. Pruebas

La imagen de producción de `api` se instala con `composer install --no-dev`: **no trae PHPUnit ni Pint**. Para correr las pruebas, instalar las dependencias de desarrollo en un contenedor de trabajo, sin tocar la imagen de producción:

1. Crear un contenedor desechable desde la imagen `credits-manager-api:latest` con `sleep infinity` como comando.
2. Instalar Composer y `composer install` dentro de él (con dependencias de desarrollo).
3. Copiar `src`, `tests`, `config`, `routes`, `database`, `lang` y `bootstrap/app.php` al contenedor.
4. Ejecutar con una `APP_KEY` cualquiera de 32 bytes en base64: `vendor/bin/phpunit --no-coverage`.

Las pruebas usan SQLite en memoria y no escriben en la base real. Las pruebas de `Redis` de integración se omiten si no hay Redis alcanzable.

Estilo: `vendor/bin/pint --test` (preset Laravel).

---

## 8. Seguridad y datos

- **Secretos:** `.env` no se versiona y está en `.gitignore`; no forma parte del contexto de construcción de `api` (solo se copia `./api`). Custodia fuera del servidor (ver [respaldos](respaldos.md), sección 8).
- **Respaldos:** no contienen `.env` ni credenciales del motor. Sí contienen datos personales y financieros; restringir el acceso a la carpeta.
- **Aislamiento entre empresas:** las consultas de negocio pasan por el alcance de empresa (`EmpresaScope`). Las tareas sin sesión que operan por empresa (mora, metas) reciben `empresa_id` de forma explícita. El respaldo es de la instancia completa: lee todas las empresas a propósito, no pasa por repositorios de negocio y no expone nada por la API.
- **Bitácora:** solo inserción, protegida por triggers en MySQL. Se respalda con la base.
- **Puertos:** la base escucha solo en `127.0.0.1`. Los demás servicios no publican puertos salvo `nginx` (80 y 443).

---

## 9. Decisiones y pendientes

- **Destino externo de respaldos:** implementado como copia opcional a un disco S3, configurable por el superadmin desde `/respaldos` (tabla `configuraciones_respaldo_externo`, sin variables de `.env`). La ruta local sigue siendo configurable aparte (`RESPALDOS_ORIGEN`). Queda pendiente de DDA decidir si usarlo y con qué proveedor.
- **Cifrado de respaldos:** los archivos van en formato estándar sin cifrar, para que DDA los abra sin la aplicación. Cifrarlos exige custodiar una clave adicional; decisión pendiente con DDA.
- **Conciliación bancaria sin respaldo:** los extractos subidos a `conciliacion_data` no entran en `respaldos:ejecutar` (no está en `config('respaldos.directorios')`); pendiente decidir si deben respaldarse como los demás archivos.
- **Retención de la bitácora:** la política de 5 años y su archivo histórico siguen pendientes (roadmap, Fase 1).
- **Imágenes de terceros:** `phpmyadmin` usa `latest`; fijar una versión queda pendiente.
