import type { ApiExpenseType } from '../types/api-expense-type';

export type ExpenseTypeFormProps = {
    expenseTypeId?: number;
    initialData?: ApiExpenseType;
    onSaved: (tipoGasto: ApiExpenseType) => void;
};
