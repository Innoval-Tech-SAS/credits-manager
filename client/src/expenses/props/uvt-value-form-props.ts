import type { ApiUvtValue } from '../types/api-uvt-value';

export type UvtValueFormProps = {
    valorUvtId?: number;
    initialData?: ApiUvtValue;
    onSaved: (valorUvt: ApiUvtValue) => void;
};
