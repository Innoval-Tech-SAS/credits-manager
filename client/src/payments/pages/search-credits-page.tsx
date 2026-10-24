import type { FC } from 'react';
import { useState } from 'react';
import { SearchX } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { ClientFullSelect } from '../../clients/components/client-full-select';
import { ClientInfoCard } from '../../clients/components/client-info-card';
import type { ApiClient } from '../../clients/types/api-client';
import type { ApiCredit } from '../../credits/types/api-credit';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Label } from '@ui/forms/components/label';
import { SkeletonBar } from '@ui/skeleton/components/skeleton-bar';
import { SkeletonStatus } from '@ui/skeleton/components/skeleton-status';
import { ActiveCreditCard } from '../components/active-credit-card';

export const SearchCreditsPage: FC = () => {
    const [cliente, setCliente] = useState<ApiClient | null>(null);

    const creditosParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), estado: 'desembolsado', per_page: '50' }).toString() : '';
    const { data: creditosData, isLoading } = useResource<PaginatedResponse<ApiCredit>>(cliente ? `/creditos?${creditosParams}` : null);
    const creditosActivos = creditosData?.data ?? [];

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Buscar crédito" />

            <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <Label htmlFor="cliente">Cliente</Label>
                <ClientFullSelect id="cliente" selected={cliente} onSelect={setCliente} />
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Selecciona el cliente para ver sus créditos activos.</p>
            </div>

            {!cliente && (
                <div className="flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-gray-200 py-16 text-center dark:border-gray-800">
                    <SearchX className="size-8 text-gray-300 dark:text-gray-600" />
                    <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        Selecciona un cliente para ver sus créditos activos.
                    </p>
                </div>
            )}

            {cliente && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <ClientInfoCard cliente={cliente} canEdit={false} onEdit={() => {}} />

                    {isLoading && (
                        <SkeletonStatus className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                            <div className="mb-5 flex items-center justify-between gap-3">
                                <SkeletonBar className="h-5 w-40" />
                                <SkeletonBar className="h-6 w-20 rounded-full" />
                            </div>
                            <div className="grid grid-cols-2 gap-x-6 gap-y-4 md:grid-cols-4">
                                {Array.from({ length: 4 }, (_, indice) => (
                                    <div key={indice} className="flex flex-col gap-2">
                                        <SkeletonBar className="h-3 w-20" />
                                        <SkeletonBar className="h-4 w-28" />
                                    </div>
                                ))}
                            </div>
                        </SkeletonStatus>
                    )}

                    {!isLoading && creditosActivos.length === 0 && (
                        <div className="flex items-center rounded-lg border border-warning-500 bg-warning-50 px-4 py-3 text-sm text-warning-600 dark:border-warning-800 dark:bg-warning-500/10 dark:text-orange-400">
                            {cliente.tercero.nombre} no tiene créditos activos.
                        </div>
                    )}

                    {!isLoading &&
                        creditosActivos.map((credito) => <ActiveCreditCard key={credito.id} credito={credito} />)}
                </div>
            )}
        </div>
    );
};
