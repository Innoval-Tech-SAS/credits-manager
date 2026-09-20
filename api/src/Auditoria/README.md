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

Para lo habitual hay helpers que arman entidad, id y valores a partir del modelo (nombre de entidad = clase en snake_case):

```php
$this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CLIENTES, "Cliente {$c->nombre} creado", $c, ['dato_derivado' => $x]);

$anteriores = $this->auditoria->instantanea($cliente);            // antes de modificar
$cliente = $this->clientes->actualizar($cliente, $datos);
$this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CLIENTES, "Cliente {$cliente->nombre} modificado", $cliente, $anteriores, motivo: $motivo, accion: AccionAuditoria::Anular);

$this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CLIENTES, "Cliente {$cliente->nombre} eliminado", $cliente);
$this->auditoria->registrarGuardado($modulo, $descripcion, $modelo);  // tras un updateOrCreate
```

`registrarModificacion` guarda **solo los campos que cambiaron** (comparados ya normalizados: `5` y `"5"` no son un cambio) y **no emite nada si no cambió ninguno**. Los datos derivados que no son columnas van en `adicionales:` (o en el 4.º argumento de `registrarCreacion`).

`registrar(accion, modulo, descripcion, entidad?, entidadId?, anteriores?, nuevos?, motivo?, autor?, empresaId?)`:

- Toma el **snapshot del autor** (usuario, rol, empresa, IP) de la sesión; sin sesión, pasa `autor:` y/o `empresaId:` (un proceso del sistema, como la marcación de mora, emite un evento por empresa con su `empresaId` explícito, nunca el de `Auth`).
- Dentro de `DB::transaction()` el evento se entrega **al confirmarse** (`DB::afterCommit`): un rollback no deja rastro de algo que no ocurrió.
- Quita solos los campos sensibles (`password`, `clave`, `token`…), trunca a la longitud de cada columna y sanea UTF-8. Nunca lanza: ante cualquier fallo el evento queda en el log de respaldo y la operación de negocio continúa.
- Para modificaciones pasa solo lo que cambió en `anteriores`/`nuevos`. Ejemplo: `EmpresaService::editarEmpresa`.
- El `empresa_id` del evento es el del autor (nulo para el superadmin); la entidad afectada va en `entidad`/`entidad_id`.

## Qué queda registrado

Todo servicio con escrituras de negocio emite su evento; los módulos son las constantes `AuditoriaConstants::MODULO_*` (y deben tener etiqueta en `client/src/audit/constants/audit-modules.ts`).

| Módulo | Procesos registrados |
|---|---|
| `auth` | Inicio y cierre de sesión, cambio de la contraseña propia |
| `empresas` | Alta, edición y logo |
| `usuarios` | Alta, edición, baja y restablecimiento de clave de usuarios; alta, edición y baja de roles; `cambio_permisos` (rol o permisos extra, con antes/después en `modulo.accion`) |
| `clientes`, `empleadores`, `terceros`, `proveedores` | Alta, edición y baja (cliente/proveedor incluyen los datos del tercero) |
| `documentos` | Archivos adjuntos subidos |
| `creditos` | Alta, aprobación, desembolso (dice si quedó sin contabilizar), cancelación, baja, cierre por refinanciación y crédito saldado |
| `pagos` | Pago y liquidación anticipada, con capital, interés, sobrante descartado, cuotas afectadas y comprobante |
| `cartera` | Mora: cuotas que pasaron a vencida (un evento por empresa y corrida) |
| `contabilidad` | Comprobantes (manuales y automáticos, `contabilizar`/`crear`), reverso, cierre y reapertura de período (con motivo), plan de cuentas, centros de costo, tipos de comprobante, reglas, documentos de terceros (alta, aplicación, anulación) e importación de apertura |
| `gastos` | Causación y anulación de gastos, tipos de gasto, municipios, actividades, valor UVT, parametrizaciones y tarifas ReteICA (incluida la vigencia que se cierra sola) |
| `auditoria` | Exportaciones y descargas de la bitácora |

No se registran a propósito: lecturas y reportes (salvo su exportación), el marcado de notificaciones como leídas, la creación automática del período contable vacío, el contador de consecutivos y los intentos de login fallidos.

## Visibilidad (se aplica en el backend)

`BitacoraAuditoriaRepository::consultaVisible` es la **única** consulta de lectura (listado y exportación). Admin y rol `auditoria`: solo su empresa. Superadmin: todas, con filtro `empresa_id` (ignorado para el resto). Acceso: superadmin, admin o permiso `auditoria.listar|exportar` (`AccesoAuditoria`).

## Operación

| Comando | Para qué |
|---|---|
| `php artisan auditoria:escuchar` | Listener de larga duración (servicio `auditoria-listener`). |
| `php artisan auditoria:estado` | Eventos esperando en el stream (¿listener caído?), pendientes y log de respaldo sin recuperar. |

Riesgo residual (pub/sub → Streams): un crash de Redis con AOF `everysec` puede perder hasta 1 s de cola; `TRUNCATE`/`DROP` de la tabla no activan los triggers (se cubre con privilegios de la BD).
