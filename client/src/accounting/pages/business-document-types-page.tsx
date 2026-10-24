import type { FC } from 'react';
import { useState } from 'react';

import { Pencil } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { ActionButton } from '@ui/action-button/components/action-button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Modal } from '@ui/modal/components/modal';
import { DataTable } from '@ui/table/components/data-table';
import { BusinessDocumentTypeForm } from '../components/business-document-type-form';
import type { ApiBusinessDocumentType } from '../types/api-business-document-type';

const NATURALEZA_LABELS: Record<ApiBusinessDocumentType['naturaleza'], string> = {
    ingreso: 'Ingreso',
    gasto: 'Gasto',
    recaudo: 'Recaudo',
    pago: 'Pago',
    ajuste: 'Ajuste',
};

const Badge: FC<{ activo: boolean; textoActivo: string; textoInactivo: string }> = ({ activo, textoActivo, textoInactivo }) => (
    <span
        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
            activo
                ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
        }`}
    >
        {activo ? textoActivo : textoInactivo}
    </span>
);

export const BusinessDocumentTypesPage: FC = () => {
    const [tipoAEditar, setTipoAEditar] = useState<ApiBusinessDocumentType | null>(null);
    const { data, isLoading, refetch } = useResource<{ tipos: ApiBusinessDocumentType[] }>('/contabilidad/tipos-documento-negocio');

    const tipos = data?.tipos ?? [];

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Naturaleza de documentos" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Catálogo de los 6 tipos de documento (factura, causación, recibo de caja, egreso, nota crédito y nota débito) que
                    participan de cuentas por cobrar y por pagar: qué naturaleza de negocio tienen y si crean o cruzan saldo. Facturación,
                    Recibos, Egresos y Causación validan contra esta configuración antes de registrar cada operación.
                </p>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <DataTable
                    columns={[
                        { key: 'codigo', label: 'Código' },
                        { key: 'nombre', label: 'Nombre' },
                        { key: 'naturaleza', label: 'Naturaleza', render: (tipo) => NATURALEZA_LABELS[tipo.naturaleza] },
                        {
                            key: 'crea_saldo',
                            label: '¿Crea saldo?',
                            render: (tipo) => <Badge activo={tipo.crea_saldo} textoActivo="Sí" textoInactivo="No" />,
                        },
                        {
                            key: 'cruza_saldo',
                            label: '¿Cruza saldo?',
                            render: (tipo) => <Badge activo={tipo.cruza_saldo} textoActivo="Sí" textoInactivo="No" />,
                        },
                        {
                            key: 'activo',
                            label: 'Estado',
                            render: (tipo) => <Badge activo={tipo.activo} textoActivo="Activo" textoInactivo="Inactivo" />,
                        },
                        {
                            key: 'accion',
                            label: '',
                            render: (tipo) => <ActionButton icon={<Pencil className="size-5" />} label="Editar" onClick={() => setTipoAEditar(tipo)} />,
                        },
                    ]}
                    rows={tipos}
                    getRowKey={(tipo) => tipo.id}
                    loading={isLoading}
                    emptyMessage="No hay tipos de documento configurados."
                    maxHeightClassName="max-h-[65vh]"
                />
            </div>

            <Modal isOpen={tipoAEditar !== null} onClose={() => setTipoAEditar(null)} size="lg">
                {tipoAEditar && (
                    <>
                        <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">
                            {tipoAEditar.codigo} — {tipoAEditar.nombre}
                        </h4>
                        <BusinessDocumentTypeForm
                            tipo={tipoAEditar}
                            onSaved={() => {
                                refetch();
                                setTipoAEditar(null);
                            }}
                        />
                    </>
                )}
            </Modal>
        </div>
    );
};
