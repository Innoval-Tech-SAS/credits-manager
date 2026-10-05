import type { ApiImport } from '../types/api-import';
import type { ApiImportDetails } from '../types/api-import-details';

export type ImportDecisionModalsProps = {
    importacion: ApiImport;
    decisiones: ApiImportDetails['decisiones'];
    isSubmitting: boolean;
    onDecide: (decision: 'crear_empleadores' | 'generar_asiento', value: boolean) => void;
};
