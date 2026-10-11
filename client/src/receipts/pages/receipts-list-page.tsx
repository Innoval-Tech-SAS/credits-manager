import type { FC } from 'react';
import { useState } from 'react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { ReceiptFilters } from '../components/receipt-filters';
import { buildReceiptTableColumns } from '../utils/build-receipt-table-columns';
import type { ApiReceipt } from '../types/api-receipt';

export const ReceiptsListPage: FC = () => {
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);
    const [estado, setEstado] = useState('');
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');

    const { items, isLoading, error, page, setPage, totalPages, totalItems } = usePaginatedResource<ApiReceipt>('/recibos', {
        tercero_id: tercero ? String(tercero.id) : '',
        estado,
        desde,
        hasta,
    });

    // Cada filtro vuelve a la primera página: una página vacía tras filtrar no tiene sentido.
    const alCambiarFiltro = <T,>(aplicar: (valor: T) => void) => (valor: T) => {
        aplicar(valor);
        setPage(1);
    };

    return (
        <ModuleListPage
            pageTitle="Recibos de caja"
            errorMessage={error}
            countLabel={`${totalItems} ${totalItems === 1 ? 'recibo' : 'recibos'}`}
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <ReceiptFilters
                tercero={tercero}
                onTerceroChange={alCambiarFiltro(setTercero)}
                estado={estado}
                onEstadoChange={alCambiarFiltro(setEstado)}
                desde={desde}
                onDesdeChange={alCambiarFiltro(setDesde)}
                hasta={hasta}
                onHastaChange={alCambiarFiltro(setHasta)}
            />

            <DataTable
                columns={buildReceiptTableColumns()}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="No hay recibos de caja con esos filtros."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
