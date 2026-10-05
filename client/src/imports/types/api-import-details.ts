import type { ApiImport } from './api-import';

/** Raw shape returned by `GET /importaciones/{id}`: la importación y qué decisiones siguen abiertas. */
export type ApiImportDetails = {
    importacion: ApiImport;
    decisiones: {
        puede_contabilizar: boolean;
        requiere_decision_empleadores: boolean;
        requiere_decision_asiento: boolean;
    };
};
