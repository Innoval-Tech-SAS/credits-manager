import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { PostingRuleFormProps } from '../props/posting-rule-form-props';
import type { ApiPostingRule } from '../types/api-posting-rule';

export const PostingRuleForm: FC<PostingRuleFormProps> = ({ evento, reglaActual, cuentas, tipos, onSaved }) => {
    const { sendRequest } = useSendRequest();

    const [cuentaDebitoId, setCuentaDebitoId] = useState(reglaActual ? String(reglaActual.cuenta_debito_id) : '');
    const [cuentaCreditoId, setCuentaCreditoId] = useState(reglaActual ? String(reglaActual.cuenta_credito_id) : '');
    const [tipoComprobanteId, setTipoComprobanteId] = useState(reglaActual ? String(reglaActual.tipo_comprobante_id) : '');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const cuentaOptions = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));
    const tipoOptions = tipos.map((tipo) => ({ value: String(tipo.id), label: `${tipo.codigo} - ${tipo.nombre}` }));

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ regla: ApiPostingRule }>('/contabilidad/reglas', {
                method: 'POST',
                body: {
                    evento,
                    cuenta_debito_id: Number(cuentaDebitoId),
                    cuenta_credito_id: Number(cuentaCreditoId),
                    tipo_comprobante_id: Number(tipoComprobanteId),
                },
            });

            onSaved(respuesta.regla);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-4">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor={`${evento}-debito`}>Cuenta débito</Label>
                        <Select id={`${evento}-debito`} options={cuentaOptions} defaultValue={cuentaDebitoId} onChange={setCuentaDebitoId} />
                    </div>

                    <div>
                        <Label htmlFor={`${evento}-credito`}>Cuenta crédito</Label>
                        <Select id={`${evento}-credito`} options={cuentaOptions} defaultValue={cuentaCreditoId} onChange={setCuentaCreditoId} />
                    </div>

                    <div>
                        <Label htmlFor={`${evento}-tipo`}>Tipo de comprobante</Label>
                        <Select id={`${evento}-tipo`} options={tipoOptions} defaultValue={tipoComprobanteId} onChange={setTipoComprobanteId} />
                    </div>
                </div>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={isSubmitting || !cuentaDebitoId || !cuentaCreditoId || !tipoComprobanteId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                    >
                        {isSubmitting ? 'Guardando…' : 'Guardar regla'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
