import type { ApiExpenseType } from '../types/api-expense-type';

export type ExpenseTypeFormProps = {
    onCreated: (tipoGasto: ApiExpenseType) => void;
};
