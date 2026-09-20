import type { ApiThirdParty } from '../../third-parties/types/api-third-party';

/** Raw shape returned by `POST /proveedores` — creación de proveedores desde el formulario unificado de terceros (`/terceros/crear`). */
export type ApiSupplier = {
    id: number;
    tercero_id: number;
    tercero: ApiThirdParty;
    activo: boolean;
    creado_en: string | null;
    /** Solo presente en la respuesta de `POST /proveedores`: si el tercero ya existía (por documento) y se reutilizó. */
    tercero_reutilizado?: boolean;
};
