import type { ApiGoalSettings } from '../types/api-goal-settings';

export type GoalSettingsFormProps = {
    configuracion: ApiGoalSettings;
    onSaved: (configuracion: ApiGoalSettings) => void;
};
