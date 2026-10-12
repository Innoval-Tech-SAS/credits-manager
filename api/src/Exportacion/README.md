# Módulo Exportacion

Exportación genérica y **asíncrona** a XLSX/CSV. No conoce ningún dominio: cada reporte solo implementa `FuenteExportable`.

## Cómo funciona

1. `POST /<reporte>/exportar` → el controlador llama a `ExportacionService::solicitar(fuente, formato, usuario)`.
2. Se autoriza (`fuente->autorizadoPara`), se cuenta (`totalFilas`, máx. **1 048 575**: el límite de Excel, con el encabezado) y se rechaza con **422** si se pasa.
3. Se crea la fila en `exportaciones`, se audita (`exportar`) y se encola `ProcesarExportacionJob` en la conexión `redis`, cola `exportaciones` (no depende de `QUEUE_CONNECTION`). Responde **202**.
4. El servicio `exportaciones-worker` escribe el archivo en streaming (memoria constante) en el volumen `exportaciones_data` (`/var/exportaciones`, **no** montado en nginx) y notifica al solicitante por WebSocket.
5. `GET /exportaciones/{id}/descargar` (solo el solicitante; otro usuario → 404) sirve el archivo y audita la descarga.

Mantenimiento: `php artisan exportaciones:limpiar --dias=7` borra archivos vencidos y archiva el registro. Lo programa el servicio `scheduler` del compose todos los días a las 03:00 (hora de Bogotá).

## Agregar un reporte nuevo (sin modificar este módulo)

**1. Implementar la interfaz** en el módulo del reporte (ejemplo real: `Auditoria/Exportaciones/FuenteBitacoraAuditoria`):

```php
final class FuenteCartera implements FuenteExportable
{
    public function __construct(private readonly int $empresaId, private readonly FiltrosCartera $filtros, private readonly ?int $hastaId = null) {}

    public function reporte(): string { return 'cartera.listado'; }      // clave estable
    public function modulo(): string { return 'cartera'; }                // módulo del evento de auditoría
    public function titulo(): string { return 'Cartera'; }
    public function nombreArchivo(): string { return 'cartera'; }         // sin extensión ni fecha

    public function columnas(): array
    {
        return [
            new ColumnaExportable('cliente', 'Cliente'),                                   // TipoColumna::Texto por defecto
            new ColumnaExportable('saldo', 'Saldo', TipoColumna::Monetario),
            new ColumnaExportable('vence', 'Vencimiento', TipoColumna::Fecha),
        ];
    }

    public function filas(): iterable { /* lazy()/cursor(), NUNCA get(): yield ['cliente' => ..., 'saldo' => $dinero, ...] */ }
    public function totalFilas(): int { /* misma consulta que el listado */ }
    public function filtrosAplicados(): array { return $this->filtros->aArray(); }
    public function autorizadoPara(Usuario $usuario): bool { return app(AccesoExportaciones::class)->puede($usuario, $this->reporte()); }
}
```

Reglas de la fuente:

- **Serializable**: viaja dentro del job. Lleva su visibilidad (empresa) y filtros de forma explícita; el worker **no tiene sesión** (`Auth` no existe). Nada de closures ni servicios inyectados: resuélvelos con `app()` al leer las filas.
- **Una sola fuente de verdad**: `filas()` y `totalFilas()` deben usar la misma consulta y las mismas reglas de visibilidad que el listado en pantalla (exportar nunca muestra más de lo que se ve).
- **Foto consistente**: captura un `id` de corte al solicitar (`hastaId`) para que lo que llegue mientras el job espera no entre al archivo.
- **Valores**: monetarios como `Dinero` o cadena decimal; fechas como `DateTimeInterface`/string; los escritores convierten (montos → celdas numéricas con formato, fechas en America/Bogota, texto neutralizado contra inyección de fórmulas).

**2. Su permiso, una ruta y un método de servicio.** Cada reporte tiene su permiso propio dentro de su módulo
(`cartera.exportar_listado`), declarado una sola vez en `ExportacionConstants::PERMISOS_POR_REPORTE` (clave del reporte
=> permisos que lo autorizan). Ese mapa lo leen la ruta (middleware `exportacion:<clave>`), `autorizadoPara` y la
descarga (`AccesoExportaciones`): quien pierde el permiso tampoco descarga lo que ya había pedido. Un reporte que no
esté en el mapa no se autoriza a nadie. El permiso nuevo va además en `PermisoSeeder::CATALOGO` y en su migración.

```php
Route::post('/cartera/exportar', [CarteraController::class, 'exportar'])->middleware('exportacion:cartera.listado');
// Service:  return $this->exportaciones->solicitar(new FuenteCartera(...), FormatoExportacion::from($validados['formato']), $usuario);
// Controller: return response()->json(['exportacion' => $exportacion], 202);
```

**3. Un botón en el frontend** (`client/src/shared/components/export-button.tsx`, el aviso de "lista" llega solo por la campana):

```tsx
<ExportButton endpoint="/cartera/exportar" filters={filters} onRequested={...} onError={...} />
```

## Punto de extensión para PDF (Fase 3)

Agregar un `case Pdf` a `FormatoExportacion` y un escritor que implemente `EscritorExportacion`. Ningún reporte cambia.
