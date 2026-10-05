export const EMAIL_SETTINGS_STEPS = [
    { id: 'servidor', label: 'Servidor' },
    { id: 'cuenta', label: 'Cuenta' },
    { id: 'mensaje', label: 'Mensaje' },
] as const;

export const EMAIL_SETTINGS_LAST_STEP = EMAIL_SETTINGS_STEPS.length - 1;
