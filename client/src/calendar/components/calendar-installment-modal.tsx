import type { FC } from 'react';
import { useNavigate } from 'react-router';
import { CalendarDays, CreditCard, Receipt } from 'lucide-react';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import { INSTALLMENT_STATUS_CLASSES, INSTALLMENT_STATUS_LABEL } from '../../credits/constants/installment-status-styles';
import type { CalendarInstallmentModalProps } from '../props/calendar-installment-modal-props';
import { CalendarClientPanel } from './calendar-client-panel';

export const CalendarInstallmentModal: FC<CalendarInstallmentModalProps> = ({ detalle, onClose }) => {
    const navigate = useNavigate();

    return (
        <Modal isOpen={detalle !== null} onClose={onClose} size="lg" className="overflow-hidden p-0! sm:p-0!">
            {detalle && (
                <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                    <CalendarClientPanel detalle={detalle} />

                    <section className="flex flex-col p-6 sm:p-8">
                        <header className="flex items-start justify-between gap-4 pr-12">
                            <div>
                                <p className="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Pago de hoy</p>
                                <h3 className="mt-1 text-xl font-semibold text-gray-900 dark:text-white/90">Cuota #{detalle.numeroCuota}</h3>
                                <p className="mt-1 flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                                    <CalendarDays className="size-4" />
                                    Vence {formatDateEs(detalle.fechaVencimiento)}
                                </p>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-xs font-medium ${INSTALLMENT_STATUS_CLASSES[detalle.estado]}`}>
                                {INSTALLMENT_STATUS_LABEL[detalle.estado]}
                            </span>
                        </header>

                        <div className="mt-6 rounded-xl border border-gray-200 px-5 py-2 dark:border-gray-800">
                            <div className="flex items-center justify-between py-3 text-sm">
                                <span className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <Receipt className="size-4 text-gray-400" /> Capital
                                </span>
                                <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(detalle.valorCapital)}</span>
                            </div>
                            <div className="flex items-center justify-between border-t border-dashed border-gray-200 py-3 text-sm dark:border-gray-700">
                                <span className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <Receipt className="size-4 text-gray-400" /> Intereses
                                </span>
                                <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(detalle.valorInteres)}</span>
                            </div>
                            <div className="flex items-center justify-between border-t border-dashed border-gray-200 py-3 text-sm dark:border-gray-700">
                                <span className="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <Receipt className="size-4 text-gray-400" /> Otros gastos
                                </span>
                                <span className="font-medium text-gray-800 dark:text-white/90">{formatMoney(detalle.valorOtrosGastos)}</span>
                            </div>
                        </div>

                        <div className="mt-4 flex items-center justify-between px-1 text-sm font-semibold text-gray-900 dark:text-white/90">
                            <span className="flex items-center gap-2">
                                <CreditCard className="size-4 text-brand-500" /> Total de la cuota
                            </span>
                            <span>{formatMoney(detalle.valorTotal)}</span>
                        </div>

                        <div className="mt-4 flex items-center justify-between rounded-xl bg-success-50 px-4 py-3 text-sm dark:bg-success-500/10">
                            <span className="text-success-700 dark:text-success-400">Pagado</span>
                            <span className="font-semibold text-success-700 dark:text-success-400">{formatMoney(detalle.valorPagado)}</span>
                        </div>

                        <div className="mt-3 flex items-center justify-between rounded-xl border-2 border-dashed border-brand-200 px-4 py-3 dark:border-brand-800">
                            <span className="font-medium text-gray-700 dark:text-gray-300">Saldo a pagar</span>
                            <span className="text-2xl font-semibold text-brand-600 dark:text-brand-400">{formatMoney(detalle.saldo)}</span>
                        </div>

                        <div className="mt-auto flex flex-col-reverse gap-3 pt-6 sm:flex-row sm:justify-end">
                            <Button variant="outline" onClick={() => navigate(`/creditos/${detalle.creditoId}`)}>
                                Ver crédito
                            </Button>
                            {detalle.estado !== 'pagada' && (
                                <Button onClick={() => navigate(`/pagos/creditos/${detalle.creditoId}/nuevo`)}>Registrar pago</Button>
                            )}
                        </div>
                    </section>
                </div>
            )}
        </Modal>
    );
};
