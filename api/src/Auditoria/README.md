# Módulo Auditoria

Bitácora de solo inserción sobre **Redis Streams**. El emisor nunca inserta en la base de datos.

```
Service ──registrar()──▶ XADD auditoria:eventos ──▶ auditoria-listener ──INSERT (idempotente)──▶ bitacora_auditoria
   │                          (persistente, AOF)      XREADGROUP BLOCK: push inmediato         XACK + XDEL
   └─ si Redis falla ──▶ storage/logs/auditoria-respaldo-*.log ──▶ panel de recuperación (superadmin)
```

## Instrumentar un servicio (una línea)

```php
public function __construct(protected ClienteRepository $clientes, protected EmisorAuditoria $auditoria) {}

// dentro de la operación de negocio, tras la escritura:
$this->auditoria->registrar(AccionAuditoria::Crear, 'clientes', "Cliente {$cliente->nombre} creado", 'cliente', $cliente->id, nuevos: $cliente->toArray());
```

`registrar(accion, modulo, descripcion, entidad?, entidadId?, anteriores?, nuevos?, motivo?, autor?)`:

- Toma el **snapshot del autor** (usuario, rol, empresa, IP) de la sesión; sin sesión, pasa `autor:`.
- Dentro de `DB::transaction()` el evento se entrega **al confirmarse** (`DB::afterCommit`): un rollback no deja rastro de algo que no ocurrió.
- Quita solos los campos sensibles (`password`, `clave`, `token`…), trunca a la longitud de cada columna y sanea UTF-8. Nunca lanza: ante cualquier fallo el evento queda en el log de respaldo y la operación de negocio continúa.
- Para modificaciones pasa solo lo que cambió en `anteriores`/`nuevos`. Ejemplo: `EmpresaService::editarEmpresa`.
- El `empresa_id` del evento es el del autor (nulo para el superadmin); la entidad afectada va en `entidad`/`entidad_id`.

Referencias ya instrumentadas: `AuthService` (login/logout) y `EmpresaService` (alta/edición).

## Visibilidad (se aplica en el backend)

`BitacoraAuditoriaRepository::consultaVisible` es la **única** consulta de lectura (listado y exportación). Admin y rol `auditoria`: solo su empresa. Superadmin: todas, con filtro `empresa_id` (ignorado para el resto). Acceso: superadmin, admin o permiso `auditoria.listar|exportar` (`AccesoAuditoria`).

## Operación

| Comando | Para qué |
|---|---|
| `php artisan auditoria:escuchar` | Listener de larga duración (servicio `auditoria-listener`). |
| `php artisan auditoria:estado` | Eventos esperando en el stream (¿listener caído?), pendientes y log de respaldo sin recuperar. |

Riesgo residual (pub/sub → Streams): un crash de Redis con AOF `everysec` puede perder hasta 1 s de cola; `TRUNCATE`/`DROP` de la tabla no activan los triggers (se cubre con privilegios de la BD).
