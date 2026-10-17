import type { ApiCredit } from '../../credits/types/api-credit';
import type { ApiSettlementQuote } from '../types/api-settlement-quote';

export type SettleCreditFormProps = {
    credito: ApiCredit;
    cotizacion: ApiSettlementQuote;
};
