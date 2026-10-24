import type { ImportStatus } from '../constants/import-status';
import type { ImportType } from '../constants/import-types';

/** Raw shape returned by `GET /importaciones` (rows), `POST /importaciones` (202) and `GET /importaciones/{id}` (`importacion`). */
export type ApiImport = {
    id: number;
    empresa_id: number;
    usuario_id: number;
    tipo: ImportType;
    estado: ImportStatus;
    archivo_original: string;
    preferencia_crear_empleadores: boolean;
    preferencia_generar_asiento: boolean;
    cuenta_contrapartida_id: number | null;
    decision_crear_empleadores: boolean | null;
    decision_generar_asiento: boolean | null;
    comprobante_id: number | null;
    total_filas: number;
    filas_aceptadas: number;
    filas_rechazadas: number;
    filas_pendientes: number;
    empleadores_pendientes: number;
    error: string | null;
    iniciada_en: string | null;
    validada_en: string | null;
    completada_en: string | null;
    creado_en: string | null;
    usuario?: { id: number; nombre: string } | null;
};
