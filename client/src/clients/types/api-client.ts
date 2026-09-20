import type { ApiEmployer } from '../../employers/types/api-employer';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';

/** Raw shape returned by `GET /clientes` (rows), `GET /clientes/{id}`, `POST /clientes` and `PATCH /clientes/{id}`. */
export type ApiClient = {
    id: number;
    tercero_id: number;
    tercero: ApiThirdParty;
    empleador_id: number | null;
    empleador: ApiEmployer | null;
    activo: boolean;
    creado_en: string | null;
    /** Solo presente en la respuesta de `POST /clientes`: si el tercero ya existía (por documento) y se reutilizó. */
    tercero_reutilizado?: boolean;
};
