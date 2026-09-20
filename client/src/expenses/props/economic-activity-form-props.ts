import type { ApiEconomicActivity } from '../types/api-economic-activity';

export type EconomicActivityFormProps = {
    onCreated: (actividad: ApiEconomicActivity) => void;
};
