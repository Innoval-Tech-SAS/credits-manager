import type { FC } from 'react';

import type { EmailTypeTabsProps } from '../props/email-type-tabs-props';
import type { EmailType } from '../types/email-type';

const PESTANAS: { value: EmailType; label: string }[] = [
    { value: 'cobranza', label: 'Cobranza' },
    { value: 'facturacion', label: 'Facturación' },
];

export const EmailTypeTabs: FC<EmailTypeTabsProps> = ({ tipo, onChange }) => (
    <div className="mb-6 inline-flex w-fit rounded-lg border border-gray-200 p-1 dark:border-gray-800" role="tablist">
        {PESTANAS.map((pestana) => (
            <button
                key={pestana.value}
                type="button"
                role="tab"
                aria-selected={tipo === pestana.value}
                onClick={() => onChange(pestana.value)}
                className={`rounded-md px-4 py-2 text-sm font-medium transition ${
                    tipo === pestana.value
                        ? 'bg-brand-500 text-white'
                        : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90'
                }`}
            >
                {pestana.label}
            </button>
        ))}
    </div>
);
