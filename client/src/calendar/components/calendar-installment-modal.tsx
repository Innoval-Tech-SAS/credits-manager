import type { FC, ReactNode } from 'react';
import { useNavigate } from 'react-router';
import { CalendarDays, CreditCard, HandCoins, Landmark, Receipt, User } from 'lucide-react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import { INSTALLMENT_STATUS_CLASSES, INSTALLMENT_STATUS_LABEL } from '../../credits/constants/installment-status-styles';
import type { InstallmentStatus } from '../../credits/types/installment-status';
import type { CalendarInstallmentModalProps } from '../props/calendar-installment-modal-props';

const CABECERA_POR_ESTADO: Record<InstallmentStatus, string> = {
    pendiente: 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
    vencida: 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
    pagada: 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
};

export const CalendarInstallmentModal: FC<CalendarInstallmentModalProps> = ({ detalle, onClose }) => {
    const navigate = useNavigate();

    return (
        <Modal isOpen={detalle !== null} onClose={onClose} size="lg">
            {detalle && (
                <div className="overflow-hidden rounded-2xl">
                    <div className={`px-6 py-6 sm:px-8 ${CABECERA_POR_ESTADO[detalle.estado]}`}>
                        <p className="text-xs font-medium tracking-wide uppercase opacity-80">
                            Cuota #{detalle.numeroCuota} · Crédito #{detalle.creditoId}
                        </p>
                        <h3 className="mt-1 text-xl font-semibold text-gray-900 dark:text-white/90">{detalle.clienteNombre}</h3>

                        <div className="mt-5 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-sm opacity-80">Saldo pendiente de la cuota</p>
                                <p className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white/90">{formatMoney(detalle.saldo)}</p>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-sm font-medium ${INSTALLMENT_STATUS_CLASSES[detalle.estado]}`}>
                                {INSTALLMENT_STATUS_LABEL[detalle.estado]}
                            </span>
                        </div>
                    </div>

                    <div className="space-y-6 bg-white p-6 sm:p-8 dark:bg-gray-900">
                        <div>
                            <h4 className="mb-3 text-sm font-semibold text-gray-800 dark:text-white/90">Desglose de la cuota</h4>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <DesgloseTile icono={<Landmark className="size-4" />} etiqueta="Capital" valor={detalle.valorCapital} />
                                <DesgloseTile icono={<Receipt className="size-4" />} etiqueta="Intereses" valor={detalle.valorInteres} />
                                <DesgloseTile icono={<HandCoins className="size-4" />} etiqueta="Otros gastos" valor={detalle.valorOtrosGastos} />
                                <DesgloseTile icono={<CreditCard className="size-4" />} etiqueta="Total de la cuota" valor={detalle.valorTotal} destacado />
                            </div>
                        </div>

                        <dl className="grid grid-cols-2 gap-4 rounded-xl border border-gray-200 p-4 text-sm sm:grid-cols-3 dark:border-gray-800">
                            <div className="flex items-start gap-2">
                                <CalendarDays className="mt-0.5 size-4 text-gray-400" />
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400">Vencimiento</dt>
                                    <dd className="mt-0.5 font-semibold text-gray-800 dark:text-white/90">{formatDateEs(detalle.fechaVencimiento)}</dd>
                                </div>
                            </div>
                            <div className="flex items-start gap-2">
                                <HandCoins className="mt-0.5 size-4 text-gray-400" />
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400">Pagado</dt>
                                    <dd className="mt-0.5 font-semibold text-gray-800 dark:text-white/90">{formatMoney(detalle.valorPagado)}</dd>
                                </div>
                            </div>
                            <div className="flex items-start gap-2">
                                <User className="mt-0.5 size-4 text-gray-400" />
                                <div>
                                    <dt className="text-gray-500 dark:text-gray-400">Crédito</dt>
                                    <dd className="mt-0.5 font-semibold text-gray-800 dark:text-white/90">#{detalle.creditoId}</dd>
                                </div>
                            </div>
                        </dl>

                        <div className="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800">
                            <Button variant="outline" onClick={() => navigate(`/creditos/${detalle.creditoId}`)}>
                                Ver crédito
                            </Button>
                            {detalle.estado !== 'pagada' && (
                                <Button onClick={() => navigate(`/pagos/creditos/${detalle.creditoId}/nuevo`)}>Registrar pago</Button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </Modal>
    );
};

type DesgloseTileProps = {
    icono: ReactNode;
    etiqueta: string;
    valor: string;
    destacado?: boolean;
};

const DesgloseTile: FC<DesgloseTileProps> = ({ icono, etiqueta, valor, destacado = false }) => (
    <div
        className={`rounded-xl border p-4 ${
            destacado
                ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-500/10'
                : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/5'
        }`}
    >
        <div className={`flex items-center gap-2 text-xs ${destacado ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400'}`}>
            {icono}
            <span>{etiqueta}</span>
        </div>
        <p className="mt-2 text-base font-semibold text-gray-800 sm:text-lg dark:text-white/90">{formatMoney(valor)}</p>
    </div>
);
