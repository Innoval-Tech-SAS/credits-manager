import type { ApiOpeningBalanceImport } from '../types/api-opening-balance-import';

export type ImportModalProps = {
    onClose: () => void;
    tipo: ApiOpeningBalanceImport['tipo'];
    titulo: string;
    columnas: string[];
    notas?: string;
    onImported: (migracion: ApiOpeningBalanceImport) => void;
};
