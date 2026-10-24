export const BUTTON_SIZE_CLASSES = {
    sm: 'px-4 py-3 text-sm',
    md: 'px-5 py-3.5 text-sm',
};

/** Cuadrado de 44 px (área táctil mínima); `belowMd` vuelve al tamaño normal con texto desde `md`. */
export const BUTTON_ICON_ONLY_CLASSES = {
    always: 'size-11 shrink-0 p-0 text-sm',
    belowMd: 'size-11 shrink-0 p-0 text-sm md:size-auto md:px-5 md:py-3.5',
};

export const BUTTON_VARIANT_CLASSES = {
    primary: 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 disabled:bg-brand-300',
    outline:
        'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03] dark:hover:text-gray-300',
};
