import type { ApiTaxObligationType } from '../types/api-tax-obligation-type';

export type EditTaxObligationTypeModalProps = {
    tipo: ApiTaxObligationType;
    onClose: () => void;
    onSaved: () => void;
};
