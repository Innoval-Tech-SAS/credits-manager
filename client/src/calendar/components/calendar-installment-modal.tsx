import type { FC } from 'react';
import { useNavigate } from 'react-router';

import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import { INSTALLMENT_STATUS_CLASSES, INSTALLMENT_STATUS_LABEL } from '../../credits/constants/installment-status-styles';
import type { CalendarInstallmentModalProps } from '../props/calendar-installment-modal-props';

export const CalendarInstallmentModal: FC<CalendarInstallmentModalProps> = ({ detalle, onClose }) => {
    const navigate = useNavigate();

    return (
        <Modal isOpen={detalle !== null} onClose={onClose} size="sm">
            {detalle && (
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">Cuota #{detalle.numeroCuota}</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{detalle.clienteNombre}</p>

                    <dl className="mt-5 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Vencimiento</dt>
                            <dd className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatDateEs(detalle.fechaVencimiento)}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Saldo</dt>
                            <dd className="mt-1 font-semibold text-gray-800 dark:text-white/90">{formatMoney(detalle.saldo)}</dd>
                        </div>
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Estado</dt>
                            <dd className="mt-1">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${INSTALLMENT_STATUS_CLASSES[detalle.estado]}`}>
                                    {INSTALLMENT_STATUS_LABEL[detalle.estado]}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-gray-500 dark:text-gray-400">Crédito</dt>
                            <dd className="mt-1 font-semibold text-gray-800 dark:text-white/90">#{detalle.creditoId}</dd>
                        </div>
                    </dl>

                    <div className="mt-6 flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end dark:border-gray-800">
                        <Button variant="outline" onClick={() => navigate(`/creditos/${detalle.creditoId}`)}>
                            Ver crédito
                        </Button>
                        <Button onClick={() => navigate(`/pagos/creditos/${detalle.creditoId}/nuevo`)}>Registrar pago</Button>
                    </div>
                </div>
            )}
        </Modal>
    );
};
