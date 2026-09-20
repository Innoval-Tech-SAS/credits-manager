import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { Label } from '@ui/forms/components/label';
import { DataTable } from '@ui/table/components/data-table';
import { DOCUMENT_STATUS_BADGE_CLASSES } from '../constants/document-status-badge-classes';
import type { ThirdPartyDocumentsTableProps } from '../props/third-party-documents-table-props';
import type { ApiThirdPartyDocument } from '../types/api-third-party-document';

export const ThirdPartyDocumentsTable: FC<ThirdPartyDocumentsTableProps> = ({ naturaleza }) => {
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);

    const { data, isLoading } = useResource<{ documentos: ApiThirdPartyDocument[] }>(
        tercero ? `/terceros/${tercero.id}/documentos-pendientes?naturaleza=${naturaleza}` : '',
    );

    const documentos = data?.documentos ?? [];

    return (
        <div className="flex flex-col gap-6">
            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <Label htmlFor="tercero">Tercero</Label>
                <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
            </div>

            {tercero && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <DataTable
                        columns={[
                            { key: 'tipo_documento', label: 'Tipo documento' },
                            { key: 'numero_documento', label: 'Número' },
                            { key: 'fecha', label: 'Fecha', render: (documento) => formatDateEs(documento.fecha) },
                            { key: 'concepto', label: 'Concepto' },
                            { key: 'valor_original', label: 'Valor original', render: (documento) => formatMoney(documento.valor_original) },
                            {
                                key: 'saldo',
                                label: 'Saldo pendiente',
                                render: (documento) => (
                                    <span className="font-semibold text-gray-800 dark:text-white/90">{formatMoney(documento.saldo)}</span>
                                ),
                            },
                            {
                                key: 'estado',
                                label: 'Estado',
                                render: (documento) => (
                                    <span
                                        className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${DOCUMENT_STATUS_BADGE_CLASSES[documento.estado]}`}
                                    >
                                        {documento.estado}
                                    </span>
                                ),
                            },
                        ]}
                        rows={documentos}
                        getRowKey={(row) => row.id}
                        loading={isLoading}
                        emptyMessage="Sin documentos pendientes."
                    />
                </div>
            )}
        </div>
    );
};
