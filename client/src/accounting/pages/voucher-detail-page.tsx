import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { usePermission } from '../../auth/hooks/use-permission';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ReverseVoucherModal } from '../components/reverse-voucher-modal';
import type { ApiVoucher } from '../types/api-voucher';
import { VOUCHER_STATUS_BADGE_CLASSES } from '../constants/voucher-status-badge-classes';

export const VoucherDetailPage: FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const canReverse = usePermission('contabilidad', 'anular');

    const { data, error, isLoading } = useResource<{ comprobante: ApiVoucher }>(`/contabilidad/comprobantes/${id}`);
    const comprobante = data?.comprobante ?? null;
    const [isReversing, setIsReversing] = useState(false);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle={comprobante ? `${comprobante.tipo_comprobante?.nombre ?? 'Comprobante'} No. ${comprobante.consecutivo}` : 'Comprobante'} />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {isLoading && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {comprobante && (
                    <>
                        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="space-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <p>Fecha: {formatDateEs(comprobante.fecha)}</p>
                                <p>Concepto: {comprobante.concepto}</p>
                                {comprobante.tercero && <p>Tercero: {comprobante.tercero.nombre}</p>}
                                {comprobante.documento_soporte && <p>Documento soporte: {comprobante.documento_soporte}</p>}
                                {comprobante.comprobante_reverso_id && (
                                    <p className="text-brand-600 dark:text-brand-400">
                                        Reversado por el comprobante #{comprobante.comprobante_reverso_id}.
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-3">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${VOUCHER_STATUS_BADGE_CLASSES[comprobante.estado]}`}>
                                    {comprobante.estado}
                                </span>
                                {canReverse && comprobante.estado === 'contabilizado' && (
                                    <Button variant="outline" size="sm" onClick={() => setIsReversing(true)}>
                                        Reversar
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-800">
                                        {['Cuenta', 'Descripción', 'Débito', 'Crédito'].map((label) => (
                                            <th key={label} className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {(comprobante.lineas ?? []).map((linea) => (
                                        <tr key={linea.id} className="border-b border-gray-100 dark:border-gray-800">
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {linea.cuenta ? `${linea.cuenta.codigo} - ${linea.cuenta.nombre}` : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{linea.descripcion ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {parseFloat(linea.debito) > 0 ? formatMoney(linea.debito) : ''}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {parseFloat(linea.credito) > 0 ? formatMoney(linea.credito) : ''}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <td colSpan={2} className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
                                            Total
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
                                            {formatMoney(comprobante.total_debito)}
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
                                            {formatMoney(comprobante.total_credito)}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            {comprobante && (
                <ReverseVoucherModal
                    isOpen={isReversing}
                    onClose={() => setIsReversing(false)}
                    voucherId={comprobante.id}
                    onReversed={(nuevoId) => navigate(`/contabilidad/comprobantes/${nuevoId}`, { replace: true, state: { message: 'Comprobante reversado correctamente.' } })}
                />
            )}
        </div>
    );
};
