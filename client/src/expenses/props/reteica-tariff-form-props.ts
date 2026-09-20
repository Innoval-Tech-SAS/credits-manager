import type { ApiEconomicActivity } from '../types/api-economic-activity';
import type { ApiMunicipality } from '../types/api-municipality';
import type { ApiReteicaTariff } from '../types/api-reteica-tariff';

export type ReteicaTariffFormProps = {
    municipios: ApiMunicipality[];
    actividades: ApiEconomicActivity[];
    onCreated: (tarifa: ApiReteicaTariff) => void;
};
