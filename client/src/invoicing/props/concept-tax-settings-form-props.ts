import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiConceptTaxSettings } from '../types/api-invoice';

export type ConceptTaxSettingsFormProps = {
    parametrizacion: ApiConceptTaxSettings;
    cuentas: ApiAccount[];
    onSaved: (parametrizacion: ApiConceptTaxSettings) => void;
};
