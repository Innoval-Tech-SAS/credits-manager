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
import { Spinner } from '@ui/spinner/components/spinner';
import { ActiveCreditCard } from '../components/active-credit-card';

export const SearchCreditsPage: FC = () => {
    const [cliente, setCliente] = useState<ApiClient | null>(null);

    const creditosParams = cliente ? new URLSearchParams({ cliente_id: String(cliente.id), estado: 'desembolsado', per_page: '50' }).toString() : '';
    const { data: creditosData, isLoading } = useResource<PaginatedResponse<ApiCredit>>(cliente ? `/creditos?${creditosParams}` : '');
    const creditosActivos = creditosData?.data ?? [];

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Buscar crédito" />

            <div className="mb-6 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                <Label htmlFor="cliente">Cliente</Label>
                <ClientFullSelect selected={cliente} onSelect={setCliente} />
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
                        <div className="flex items-center justify-center rounded-2xl border border-gray-200 p-8 dark:border-gray-800">
                            <Spinner size="lg" />
                        </div>
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
