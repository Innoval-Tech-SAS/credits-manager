import type { ApiExpenseType } from '../types/api-expense-type';
import type { ApiMunicipality } from '../types/api-municipality';

export type ExpenseFormProps = {
    tiposGasto: ApiExpenseType[];
    municipios: ApiMunicipality[];
};
