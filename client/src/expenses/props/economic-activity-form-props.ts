import type { ApiEconomicActivity } from '../types/api-economic-activity';

export type EconomicActivityFormProps = {
    activityId?: number;
    initialData?: ApiEconomicActivity;
    onSaved: (actividad: ApiEconomicActivity) => void;
};
