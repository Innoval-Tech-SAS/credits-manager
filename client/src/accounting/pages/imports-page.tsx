import type { FC } from 'react';
import { useState } from 'react';
import { Search, Upload } from 'lucide-react';

import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Modal } from '@ui/modal/components/modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { ImportModal } from '../components/import-modal';
import { IMPORT_TYPE_LABELS } from '../constants/import-type-labels';
import type { ApiOpeningBalanceImport } from '../types/api-opening-balance-import';

type TipoImportable = 'plan_cuentas' | 'saldos_iniciales';

const IMPORT_DEFINITIONS: Record<TipoImportable, { titulo: string; columnas: string[] }> = {
    plan_cuentas: { titulo: 'Importar plan de cuentas', columnas: ['codigo', 'nombre', 'naturaleza', 'nivel', 'tipo'] },
    saldos_iniciales: { titulo: 'Importar saldos iniciales', columnas: ['codigo_cuenta', 'fecha_corte', 'debito', 'credito'] },
};

export const ImportsPage: FC = () => {
    const [mensaje, setMensaje] = useState<string | null>(null);
    const [tipoAImportar, setTipoAImportar] = useState<TipoImportable | null>(null);
    const [migracionSeleccionada, setMigracionSeleccionada] = useState<ApiOpeningBalanceImport | null>(null);

    const { items, isLoading, page, setPage, totalPages, totalItems, search, setSearch, refetch } =
        usePaginatedResource<ApiOpeningBalanceImport>('/contabilidad/migraciones-apertura');

    const handleImported = (migracion: ApiOpeningBalanceImport) => {
        setMensaje(`Importación finalizada: ${migracion.registros_aceptados} aceptados, ${migracion.registros_rechazados} rechazados.`);
        refetch();
    };

    return (
        <ModuleListPage
            pageTitle="Importaciones"
            successMessage={mensaje}
            onDismissSuccess={() => setMensaje(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'importación registrada' : 'importaciones registradas'}`}
            toolbar={
                <>
                    <div className="w-full sm:w-72">
                        <Input
                            placeholder="Buscar por archivo…"
                            startIcon={<Search className="size-4" />}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <Button collapseBelow="md" variant="outline" startIcon={<Upload className="size-4" />} onClick={() => setTipoAImportar('plan_cuentas')}>
                        Importar plan de cuentas
                    </Button>
                    <Button collapseBelow="md" variant="outline" startIcon={<Upload className="size-4" />} onClick={() => setTipoAImportar('saldos_iniciales')}>
                        Importar saldos iniciales
                    </Button>
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <>
                    {tipoAImportar && (
                        <ImportModal
                            onClose={() => setTipoAImportar(null)}
                            tipo={tipoAImportar}
                            titulo={IMPORT_DEFINITIONS[tipoAImportar].titulo}
                            columnas={IMPORT_DEFINITIONS[tipoAImportar].columnas}
                            onImported={handleImported}
                        />
                    )}

                    <Modal isOpen={migracionSeleccionada !== null} onClose={() => setMigracionSeleccionada(null)} size="lg">
                        <h3 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Motivos de rechazo</h3>
                        <div className="custom-scrollbar max-h-96 overflow-y-auto">
                            <ul className="list-disc space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                                {migracionSeleccionada?.detalle_errores?.map((mensaje, indice) => <li key={indice}>{mensaje}</li>)}
                            </ul>
                        </div>
                    </Modal>
                </>
            }
        >
            <DataTable
                columns={[
                    { key: 'creado_en', label: 'Fecha' },
                    { key: 'tipo', label: 'Tipo', render: (row) => IMPORT_TYPE_LABELS[row.tipo] ?? row.tipo },
                    { key: 'archivo_original', label: 'Archivo' },
                    { key: 'registros_aceptados', label: 'Aceptados' },
                    {
                        key: 'registros_rechazados',
                        label: 'Rechazados',
                        render: (row) => (
                            <div className="flex items-center gap-2">
                                <span>{row.registros_rechazados}</span>
                                {row.registros_rechazados > 0 && row.detalle_errores && (
                                    <button
                                        type="button"
                                        onClick={() => setMigracionSeleccionada(row)}
                                        className="text-sm font-medium text-brand-500 hover:text-brand-600"
                                    >
                                        Ver detalle
                                    </button>
                                )}
                            </div>
                        ),
                    },
                ]}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                emptyMessage="Sin importaciones registradas."
            />
        </ModuleListPage>
    );
};
