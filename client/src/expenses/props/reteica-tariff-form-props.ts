import type { ApiEconomicActivity } from '../types/api-economic-activity';
import type { ApiReteicaTariff } from '../types/api-reteica-tariff';

export type ReteicaTariffFormProps = {
    actividades: ApiEconomicActivity[];
    onCreated: (tarifa: ApiReteicaTariff) => void;
};
