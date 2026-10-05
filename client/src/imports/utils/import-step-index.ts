import type { ImportStatus } from '../constants/import-status';

/** Índice del paso activo en el asistente (0 = Preparar … 5 = Resultado) para cada estado de la importación. */
export function importStepIndex(estado: ImportStatus): number {
    switch (estado) {
        case 'pendiente':
        case 'validando':
            return 2;
        case 'pendiente_decision':
            return 3;
        case 'importando':
            return 4;
        default:
            return 5;
    }
}
