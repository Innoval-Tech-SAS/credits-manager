import type { ApiImport } from '../types/api-import';
import type { ApiImportDetails } from '../types/api-import-details';

export type ImportDecisionModalsProps = {
    importacion: ApiImport;
    decisiones: ApiImportDetails['decisiones'];
    isSubmitting: boolean;
    /** `empleadores.crear`: sin él solo se puede rechazar (el backend responde 403 al aceptar). */
    canCreateEmployers: boolean;
    /** `contabilidad.crear_manual`: sin él solo se puede rechazar el asiento. */
    canPostEntries: boolean;
    onDecide: (decision: 'crear_empleadores' | 'generar_asiento', value: boolean) => void;
};
