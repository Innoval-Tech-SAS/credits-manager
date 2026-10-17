import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { Save } from 'lucide-react';
import { SettlementQuoteCard } from './settlement-quote-card';
import { PAYMENT_METHOD_OPTIONS } from '../constants/payment-method-options';
import type { SettleCreditFormProps } from '../props/settle-credit-form-props';
import type { PaymentMethod } from '../types/payment-method';

export const SettleCreditForm: FC<SettleCreditFormProps> = ({ credito, cotizacion }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [formaPago, setFormaPago] = useState<PaymentMethod>('efectivo');
    const [observacion, setObservacion] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest(`/creditos/${credito.id}/liquidacion`, {
                method: 'POST',
                body: { forma_pago: formaPago, observacion: observacion || null },
            });

            navigate(`/pagos/creditos/${credito.id}`, { replace: true, state: { message: 'Crédito liquidado correctamente.' } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <>
            <SettlementQuoteCard cotizacion={cotizacion} />

            <form onSubmit={handleSubmit}>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    {error && (
                        <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 sm:col-span-2 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                            {error}
                        </div>
                    )}

                    <div>
                        <Label htmlFor="forma-pago">Forma de pago</Label>
                        <Select
                            id="forma-pago"
                            options={PAYMENT_METHOD_OPTIONS}
                            defaultValue={formaPago}
                            onChange={(value) => setFormaPago(value as PaymentMethod)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="observacion">Observación (opcional)</Label>
                        <Input
                            id="observacion"
                            name="observacion"
                            value={observacion}
                            onChange={(event) => setObservacion(event.target.value)}
                        />
                    </div>

                    <p className="text-sm text-gray-500 sm:col-span-2 dark:text-gray-400">
                        Esta acción cierra el crédito por completo: todas las cuotas quedan pagadas y no se puede deshacer.
                    </p>

                    <div className="flex gap-3 border-t border-gray-100 pt-6 sm:col-span-2 dark:border-gray-800">
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                            endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                        >
                            {isSubmitting ? 'Liquidando…' : 'Confirmar liquidación'}
                        </Button>
                        <Button type="button" variant="outline" onClick={() => navigate(`/pagos/creditos/${credito.id}`)}>
                            Cancelar
                        </Button>
                    </div>
                </div>
            </form>
        </>
    );
};
