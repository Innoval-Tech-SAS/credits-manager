import type { ApiBankAccount } from '../types/api-bank-account';

export type BankAccountFormProps = {
    onCreated: (cuenta: ApiBankAccount) => void;
};
