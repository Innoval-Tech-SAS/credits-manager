import type { ApiBankAccount } from '../types/api-bank-account';

export const BANK_LABELS: Record<ApiBankAccount['banco'], string> = {
    bancolombia: 'Bancolombia',
};

export const BANK_OPTIONS = (Object.keys(BANK_LABELS) as ApiBankAccount['banco'][]).map((valor) => ({
    value: valor,
    label: BANK_LABELS[valor],
}));

export const BANK_ACCOUNT_TYPE_LABELS: Record<ApiBankAccount['tipo_cuenta'], string> = {
    ahorros: 'Ahorros',
    corriente: 'Corriente',
};

export const BANK_ACCOUNT_TYPE_OPTIONS = (Object.keys(BANK_ACCOUNT_TYPE_LABELS) as ApiBankAccount['tipo_cuenta'][]).map((valor) => ({
    value: valor,
    label: BANK_ACCOUNT_TYPE_LABELS[valor],
}));
