import type { FC } from 'react';

import { CornerDownRight } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import { DOCUMENT_STATUS_BADGE_CLASSES, DOCUMENT_STATUS_LABELS } from '../constants/document-status-badge-classes';
import type { DocumentTraceabilityModalProps } from '../props/document-traceability-modal-props';
import type { ApiDocumentTraceability } from '../types/api-document-traceability';

const MODULO_APLICACION_LABELS: Record<string, string> = {
    egresos: 'Egreso',
    egresos_reversa: 'Reversa de egreso',
    facturacion_cobro: 'Recibo de caja',
    facturacion_cobro_reversa: 'Reversa de cobro',
    facturacion_nota: 'Nota crédito/débito',
    contabilidad_manual: 'Comprobante manual',
    contabilidad_manual_reverso: 'Reverso de comprobante manual',
};

export const DocumentTraceabilityModal: FC<DocumentTraceabilityModalProps> = ({ documentoTerceroId, isOpen, onClose }) => {
    const { data, isLoading } = useResource<ApiDocumentTraceability>(isOpen ? `/contabilidad/documentos-tercero/${documentoTerceroId}/trazabilidad` : null);

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="lg">
            <h4 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Trazabilidad del documento</h4>

            {isLoading && (
                <div className="flex justify-center py-8">
                    <Spinner />
                </div>
            )}

            {!isLoading && data && (
                <div className="space-y-4">
                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/5">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="font-semibold text-gray-800 dark:text-white/90">
                                    {data.documento.numero_documento} · {data.documento.tercero.nombre}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {data.documento.tipo_documento} · {formatDateEs(data.documento.fecha)}
                                </p>
                            </div>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${DOCUMENT_STATUS_BADGE_CLASSES[data.documento.estado]}`}>
                                {DOCUMENT_STATUS_LABELS[data.documento.estado]}
                            </span>
                        </div>
                        <div className="mt-3 flex gap-6 text-sm">
                            <span className="text-gray-500 dark:text-gray-400">
                                Valor original: <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(data.documento.valor_original)}</span>
                            </span>
                            <span className="text-gray-500 dark:text-gray-400">
                                Saldo actual: <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(data.documento.saldo)}</span>
                            </span>
                        </div>
                    </div>

                    {data.pasos.length === 0 ? (
                        <p className="text-sm text-gray-400 dark:text-gray-500">Este documento todavía no tiene ninguna aplicación.</p>
                    ) : (
                        <ul className="space-y-2 pl-4">
                            {data.pasos.map((paso) => (
                                <li key={paso.id} className="flex items-start gap-2 border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                                    <CornerDownRight className="mt-0.5 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                                    <div className="flex-1">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium text-gray-800 dark:text-white/90">
                                                {MODULO_APLICACION_LABELS[paso.modulo_aplicacion] ?? paso.modulo_aplicacion}
                                            </p>
                                            <span className="text-xs text-gray-400 dark:text-gray-500">{formatDateEs(paso.fecha)}</span>
                                        </div>
                                        <p className="text-sm text-gray-600 dark:text-gray-300">
                                            {formatMoney(paso.valor_aplicado)} — saldo resultante: {formatMoney(paso.saldo_resultante)}
                                        </p>
                                        {paso.reversado_en && (
                                            <p className="text-xs text-error-600 dark:text-error-400">Reversada el {formatDateEs(paso.reversado_en)}</p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </Modal>
    );
};
