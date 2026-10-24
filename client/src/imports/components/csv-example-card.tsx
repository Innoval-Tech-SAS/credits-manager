import type { ReactElement, ReactNode } from 'react';

import { DataTable } from '@ui/table/components/data-table';

export type CsvExampleColumn = {
    key: string;
    label: string;
};

export type CsvExampleValue = string | number | null | undefined;

export type CsvExampleCardProps<T extends Record<string, CsvExampleValue> = Record<string, CsvExampleValue>> = {
    icon: ReactNode;
    title: string;
    subtitle?: string;
    columns: CsvExampleColumn[];
    example: T;
};

export function CsvExampleCard<T extends Record<string, CsvExampleValue>>({
    icon,
    title,
    subtitle,
    columns,
    example,
}: CsvExampleCardProps<T>): ReactElement {
    return (
        <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
            <div className="mb-4 flex items-start gap-3">
                <div className="flex size-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300">
                    {icon}
                </div>

                <div>
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">{title}</h2>
                    {subtitle && <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <DataTable<T>
                    columns={columns.map(({ key, label }) => ({
                        key,
                        label,
                        render: (row: T) => (row[key as keyof T] as CsvExampleValue | undefined) ?? '—',
                    }))}
                    rows={[example]}
                    getRowKey={() => 'csv-example'}
                    emptyMessage="Sin ejemplo."
                />
            </div>
        </section>
    );
}
