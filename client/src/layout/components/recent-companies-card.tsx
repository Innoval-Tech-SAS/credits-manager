import type { FC } from 'react';
import { Link } from 'react-router';
import { Building2 } from 'lucide-react';

import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import type { RecentCompaniesCardProps } from '../props/recent-companies-card-props';

export const RecentCompaniesCard: FC<RecentCompaniesCardProps> = ({ empresas }) => (
    <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
        <div className="mb-4 flex items-center justify-between gap-2">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Empresas recientes</h2>
            <Link to="/empresas" className="text-theme-xs font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400">
                Ver todo
            </Link>
        </div>

        {empresas.length === 0 ? (
            <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Aún no hay empresas registradas.</p>
        ) : (
            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {empresas.map((empresa) => (
                    <li key={empresa.id} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                            <Building2 className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-gray-800 dark:text-white/90">{empresa.nombre_empresa}</p>
                            <p className="text-theme-xs text-gray-500 dark:text-gray-400">
                                {empresa.creado_en ? `Creada ${formatDateTimeEs(empresa.creado_en)}` : 'Fecha de alta no disponible'}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
        )}
    </section>
);
