import type { ApiCarteraEdades } from '../types/api-cartera-document';

export type CarteraAgingStripProps = {
    edades: ApiCarteraEdades | null;
    isLoading: boolean;
};
