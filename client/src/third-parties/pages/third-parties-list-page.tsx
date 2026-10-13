import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Search, Upload, UserPlus } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { CreateButton } from '@ui/button/components/create-button';
import { Input } from '@ui/forms/components/input';
import { Select } from '@ui/forms/components/select';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { THIRD_PARTY_TYPE_OPTIONS } from '../constants/third-party-type-options';
import { buildThirdPartyTableColumns } from '../utils/build-third-party-table-columns';
import type { ApiThirdParty } from '../types/api-third-party';
import type { ThirdPartiesListLocationState } from '../types/third-parties-list-location-state';

export const ThirdPartiesListPage: FC = () => {
    const navigate = useNavigate();

    const canCreate = usePermission('terceros', 'crear');
    const canEdit = usePermission('terceros', 'editar');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ThirdPartiesListLocationState>();

    const [tipo, setTipo] = useState('');

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, sortKey, sortDirection, toggleSort } =
        usePaginatedResource<ApiThirdParty>('/terceros', { tipo });

    const handleTipoChange = (value: string) => {
        setTipo(value);
        setPage(1);
    };

    const columns = buildThirdPartyTableColumns(canEdit);

    return (
        <ModuleListPage
            pageTitle="Terceros"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'tercero registrado' : 'terceros registrados'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            id="buscar-tercero"
                            name="buscar-tercero"
                            placeholder="Buscar por nombre o documento…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-48">
                        <Select
                            id="filtro-tipo-tercero"
                            ariaLabel="Tipo de tercero"
                            options={THIRD_PARTY_TYPE_OPTIONS}
                            placeholder="Todos los tipos"
                            placeholderSelectable
                            defaultValue={tipo}
                            onChange={handleTipoChange}
                        />
                    </div>

                    {canCreate && (
                        <Button iconOnly variant="outline" startIcon={<Upload className="size-4" />} onClick={() => navigate('/importaciones/nueva?tipo=terceros')}>
                            Importar
                        </Button>
                    )}

                    {canCreate && <CreateButton label="tercero" icon={<UserPlus className="size-4" />} onClick={() => navigate('/terceros/crear')} />}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            <DataTable
                columns={columns}
                rows={items}
                getRowKey={(row) => row.id}
                sortKey={sortKey}
                sortDirection={sortDirection}
                onSortChange={toggleSort}
                loading={isLoading}
                emptyMessage="No hay terceros que coincidan con la búsqueda."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
