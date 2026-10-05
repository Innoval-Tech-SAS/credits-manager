import type { FC } from 'react';

import type { EmailSettingsStepperProps } from '../props/email-settings-stepper-props';

/** En móvil muestra el paso actual con una barra; desde md, los tres pasos completos. */
export const EmailSettingsStepper: FC<EmailSettingsStepperProps> = ({ pasos, pasoActual }) => (
    <nav aria-label="Pasos de la configuración">
        <div className="md:hidden">
            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                Paso {pasoActual + 1} de {pasos.length}: {pasos[pasoActual].label}
            </p>
            <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                <div className="h-full rounded-full bg-brand-500 transition-all" style={{ width: `${((pasoActual + 1) / pasos.length) * 100}%` }} />
            </div>
        </div>

        <ol className="hidden md:flex md:items-center md:gap-3">
            {pasos.map((paso, indice) => {
                const activo = indice <= pasoActual;
                const actual = indice === pasoActual;

                return (
                    <li key={paso.id} className="flex flex-1 items-center gap-3" aria-current={actual ? 'step' : undefined}>
                        <span
                            className={
                                activo
                                    ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white'
                                    : 'flex size-8 shrink-0 items-center justify-center rounded-full border border-gray-300 text-sm font-semibold text-gray-500 dark:border-gray-700 dark:text-gray-400'
                            }
                        >
                            {indice + 1}
                        </span>
                        <span className={actual ? 'text-sm font-semibold text-gray-800 dark:text-white/90' : 'text-sm text-gray-500 dark:text-gray-400'}>
                            {paso.label}
                        </span>
                        {indice < pasos.length - 1 && <span className="ml-auto h-px flex-1 bg-gray-200 dark:bg-gray-800" />}
                    </li>
                );
            })}
        </ol>
    </nav>
);
