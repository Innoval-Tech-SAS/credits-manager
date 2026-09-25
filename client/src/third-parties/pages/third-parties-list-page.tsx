import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { Search, Upload, UserPlus } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { usePermission } from '../../auth/hooks/use-permission';
import { ImportModal } from '../../accounting/components/import-modal';
import type { ApiOpeningBalanceImport } from '../../accounting/types/api-opening-balance-import';
import { Button } from '@ui/button/components/button';
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

    const canCreate = usePermission('contabilidad', 'crear');
    const canEdit = usePermission('contabilidad', 'editar');

    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<ThirdPartiesListLocationState>();

    const [tipo, setTipo] = useState('');
    const [showImportModal, setShowImportModal] = useState(false);

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, sortKey, sortDirection, toggleSort, refetch } =
        usePaginatedResource<ApiThirdParty>('/terceros', { tipo });

    const handleImported = (migracion: ApiOpeningBalanceImport) => {
        setSuccessMessage(`Importación finalizada: ${migracion.registros_aceptados} aceptados, ${migracion.registros_rechazados} rechazados.`);
        refetch();
    };

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
                            placeholder="Buscar por nombre o documento…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="w-full sm:w-48">
                        <Select
                            options={THIRD_PARTY_TYPE_OPTIONS}
                            placeholder="Todos los tipos"
                            placeholderSelectable
                            defaultValue={tipo}
                            onChange={handleTipoChange}
                        />
                    </div>

                    {canCreate && (
                        <Button iconOnly variant="outline" startIcon={<Upload className="size-4" />} onClick={() => setShowImportModal(true)}>
                            Importar
                        </Button>
                    )}

                    {canCreate && (
                        <Button collapseBelow="md" startIcon={<UserPlus className="size-4" />} onClick={() => navigate('/terceros/crear')}>
                            Crear tercero
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                showImportModal && (
                    <ImportModal
                        onClose={() => setShowImportModal(false)}
                        tipo="terceros"
                        titulo="Importar terceros"
                        columnas={['tipo', 'tipo_persona', 'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'razon_social', 'digito_verificacion', 'representante_legal']}
                        notas='"tipo": cliente, proveedor u otro. "tipo_persona": natural o juridica. Naturales: tipo_documento (cc, ce o pasaporte), nombres y apellidos. Jurídicas: razon_social, NIT sin dígito en numero_documento, digito_verificacion y representante_legal. Los clientes solo pueden ser naturales.'
                        onImported={handleImported}
                    />
                )
            }
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
            />
        </ModuleListPage>
    );
};
