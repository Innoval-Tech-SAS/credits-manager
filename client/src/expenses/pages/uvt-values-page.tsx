import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { FileText, Pencil, Trash2 } from 'lucide-react';

import { useLocationSuccessMessage } from '../../shared/hooks/use-location-success-message';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import type { UvtValuesListLocationState } from '../types/uvt-values-list-location-state';
import type { ApiUvtValue } from '../types/api-uvt-value';

export const UvtValuesPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest, isLoading } = useSendRequest();

    const canManage = usePermission('gastos', 'parametrizar');
    const [successMessage, setSuccessMessage] = useLocationSuccessMessage<UvtValuesListLocationState>();
    const { data, isLoading: isLoadingValores, refetch } = useResource<{ valores_uvt: ApiUvtValue[] }>('/gastos/valores-uvt');
    const valoresUvt = data?.valores_uvt ?? [];

    const [porEliminar, setPorEliminar] = useState<ApiUvtValue | null>(null);
    const [error, setError] = useState<string | null>(null);

    const confirmarEliminar = async () => {
        if (porEliminar === null) {
            return;
        }

        setError(null);

        try {
            await sendRequest(`/gastos/valores-uvt/${porEliminar.id}`, { method: 'DELETE' });
            setPorEliminar(null);
            void refetch();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <ModuleListPage
            pageTitle="Valores UVT"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={error}
            onDismissError={() => setError(null)}
            countLabel={`${valoresUvt.length} ${valoresUvt.length === 1 ? 'año registrado' : 'años registrados'}`}
            toolbar={
                canManage && (
                    <Button collapseBelow="md" startIcon={<FileText className="size-4" />} onClick={() => navigate('/gastos/valores-uvt/crear')}>
                        Guardar valor UVT
                    </Button>
                )
            }
            modals={
                <ConfirmModal
                    isOpen={porEliminar !== null}
                    onClose={() => setPorEliminar(null)}
                    onConfirm={() => void confirmarEliminar()}
                    variant="danger"
                    title="Eliminar valor UVT"
                    description={`El valor UVT del año ${porEliminar?.anio} se eliminará. Esta acción no se puede deshacer.`}
                    confirmLabel="Eliminar"
                    isConfirming={isLoading}
                />
            }
        >
            <DataTable<ApiUvtValue>
                columns={[
                    { key: 'anio', label: 'Año' },
                    { key: 'valor', label: 'Valor UVT' },
                    ...(canManage
                        ? [
                              {
                                  key: 'acciones',
                                  label: 'Acciones',
                                  render: (valorUvt: ApiUvtValue) => (
                                      <div className="flex items-center gap-3">
                                          <button
                                              type="button"
                                              onClick={() => navigate(`/gastos/valores-uvt/${valorUvt.id}/editar`)}
                                              className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                          >
                                              <Pencil className="size-4" />
                                              Editar
                                          </button>
                                          <button
                                              type="button"
                                              onClick={() => setPorEliminar(valorUvt)}
                                              className="inline-flex items-center gap-1.5 text-sm font-medium text-error-600 hover:text-error-700 dark:text-error-400"
                                          >
                                              <Trash2 className="size-4" />
                                              Eliminar
                                          </button>
                                      </div>
                                  ),
                              },
                          ]
                        : []),
                ]}
                rows={valoresUvt}
                getRowKey={(row) => row.id}
                loading={isLoadingValores}
                emptyMessage="Sin valores de UVT registrados."
            />
        </ModuleListPage>
    );
};
