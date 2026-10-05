export type EmailSettingsStepperProps = {
    pasos: readonly { id: string; label: string }[];
    pasoActual: number;
};
