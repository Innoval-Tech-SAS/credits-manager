import type { FC } from 'react';

import type { EmailVariablePickerProps } from '../props/email-variable-picker-props';

/** Lista de variables en una sola columna, con scroll propio para no alargar la pantalla. Al elegir una se inserta en el campo activo. */
export const EmailVariablePicker: FC<EmailVariablePickerProps> = ({ variables, onPick }) => (
    <div className="flex flex-col gap-2">
        <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Variables disponibles</p>
        <p className="text-xs text-gray-500 dark:text-gray-400">
            Elija una para insertarla donde está el cursor. Al enviar se reemplaza por el dato de cada cliente.
        </p>
        <ul className="custom-scrollbar flex max-h-72 flex-col gap-2 overflow-y-auto pr-2">
            {variables.map((variable) => (
                <li key={variable.clave} className="min-w-0">
                    <button
                        type="button"
                        onClick={() => onPick(variable.clave)}
                        className="flex w-full min-w-0 flex-col items-start gap-1 rounded-lg border border-gray-200 px-3 py-2.5 text-left hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5"
                    >
                        <code className="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-white/10 dark:text-white/90">{`{${variable.clave}}`}</code>
                        <span className="text-sm wrap-break-word text-gray-700 dark:text-gray-300">{variable.descripcion}</span>
                        <span className="text-xs break-all text-gray-500 dark:text-gray-400">Tabla: {variable.origen}</span>
                    </button>
                </li>
            ))}
        </ul>
    </div>
);
