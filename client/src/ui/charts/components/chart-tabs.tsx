import type { FC } from 'react';

import type { ChartTabsProps } from '../props/chart-tabs-props';

/** Selector de gráfico tipo TailAdmin (`components/common/ChartTab.tsx`), con las opciones que traiga cada caso de uso. */
export const ChartTabs: FC<ChartTabsProps> = ({ options, value, onChange }) => (
    <div className="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
        {options.map((option) => (
            <button
                key={option.value}
                type="button"
                onClick={() => onChange(option.value)}
                className={`w-full rounded-md px-3 py-2 text-theme-sm font-medium hover:text-gray-900 dark:hover:text-white ${
                    value === option.value ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 dark:text-gray-400'
                }`}
            >
                {option.label}
            </button>
        ))}
    </div>
);
