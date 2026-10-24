import type { ApiTaxObligation } from '../types/api-tax-obligation';
import type { ApiTaxObligationResponsible } from '../types/api-tax-obligation-responsible';
import type { ApiTaxObligationType } from '../types/api-tax-obligation-type';

export type TaxObligationEventModalProps = {
    isOpen: boolean;
    /** `null` = modo crear (se usa `fechaInicial` como fecha límite de partida). */
    obligacion: ApiTaxObligation | null;
    fechaInicial: string | null;
    tipos: ApiTaxObligationType[];
    responsables: ApiTaxObligationResponsible[];
    onClose: () => void;
    onSaved: () => void;
};
