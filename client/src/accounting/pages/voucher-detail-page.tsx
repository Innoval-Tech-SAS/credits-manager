import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router';
import { BadgeCheck, Ban, ClipboardCheck, Download, Undo2 } from 'lucide-react';

import { useDownloadFile } from '../../shared/hooks/use-download-file';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
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
    const canPrint = usePermission('contabilidad', 'imprimir_comprobante');
    const canReview = usePermission('contabilidad', 'revisar');
    const canPublish = usePermission('contabilidad', 'contabilizar');

    const { data, error, isLoading, refetch } = useResource<{ comprobante: ApiVoucher }>(`/contabilidad/comprobantes/${id}`);
    const comprobante = data?.comprobante ?? null;
    const [modo, setModo] = useState<'reversar' | 'anular' | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [isActing, setIsActing] = useState(false);
    const { sendRequest } = useSendRequest();
    const { download, isDownloading, error: downloadError } = useDownloadFile();

    const runAction = async (accion: 'revisar' | 'contabilizar') => {
        setActionError(null);
        setIsActing(true);

        try {
            await sendRequest(`/contabilidad/comprobantes/${id}/${accion}`, { method: 'POST' });
            refetch();
        } catch (caught) {
            setActionError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsActing(false);
        }
    };

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
                                {comprobante.revisor && <p>Revisado por: {comprobante.revisor.nombre}</p>}
                                {downloadError && <p className="text-error-600 dark:text-error-400">{downloadError}</p>}
                {actionError && <p className="text-error-600 dark:text-error-400">{actionError}</p>}
                                {comprobante.comprobante_reverso_id && (
                                    <p className="text-brand-600 dark:text-brand-400">
                                        Reversado por el comprobante #{comprobante.comprobante_reverso_id}.
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${VOUCHER_STATUS_BADGE_CLASSES[comprobante.estado]}`}>
                                    {comprobante.estado}
                                </span>
                                <div className="ml-auto flex items-center gap-3">
                                    {canPrint && (
                                        <Button
                                            iconOnly
                                            variant="outline"
                                            label="Descargar PDF"
                                            startIcon={<Download className="size-4" />}
                                            disabled={isDownloading}
                                            onClick={() => download(`/contabilidad/comprobantes/${comprobante.id}/pdf`, `comprobante-${comprobante.id}.pdf`)}
                                        >
                                            {isDownloading ? 'Generando…' : 'Descargar PDF'}
                                        </Button>
                                    )}
                                    {canReview && comprobante.estado === 'borrador' && (
                                        <Button iconOnly variant="outline" startIcon={<ClipboardCheck className="size-4" />} disabled={isActing} onClick={() => void runAction('revisar')}>
                                            Revisar
                                        </Button>
                                    )}
                                    {canPublish && comprobante.estado === 'revisado' && (
                                        <Button iconOnly startIcon={<BadgeCheck className="size-4" />} disabled={isActing} onClick={() => void runAction('contabilizar')}>
                                            Contabilizar
                                        </Button>
                                    )}
                                    {canReverse && (comprobante.estado === 'borrador' || comprobante.estado === 'revisado') && (
                                        <Button iconOnly variant="outline" startIcon={<Ban className="size-4" />} onClick={() => setModo('anular')}>
                                            Anular
                                        </Button>
                                    )}
                                    {canReverse && comprobante.estado === 'contabilizado' && (
                                        <Button iconOnly variant="outline" startIcon={<Undo2 className="size-4" />} onClick={() => setModo('reversar')}>
                                            Reversar
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full min-w-240 text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-800">
                                        {['Cuenta', 'Centro de costo', 'Doc. cruce', 'Vence', 'Descripción', 'Débito', 'Crédito'].map((label) => (
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
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {linea.centro_costo ? `${linea.centro_costo.codigo} - ${linea.centro_costo.nombre}` : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{linea.documento_cruce ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {linea.fecha_vencimiento ? formatDateEs(linea.fecha_vencimiento) : '—'}
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
                                        <td colSpan={5} className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
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
                    isOpen={modo !== null}
                    onClose={() => setModo(null)}
                    voucherId={comprobante.id}
                    sinEfecto={modo === 'anular'}
                    onReversed={(nuevoId) => {
                        setModo(null);

                        if (nuevoId === comprobante.id) {
                            refetch();
                            return;
                        }

                        navigate(`/contabilidad/comprobantes/${nuevoId}`, { replace: true, state: { message: 'Comprobante reversado correctamente.' } });
                    }}
                />
            )}
        </div>
    );
};
