import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { Hash } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiAccount } from '../../accounting/types/api-account';
import { BANK_ACCOUNT_TYPE_OPTIONS, BANK_OPTIONS } from '../constants/bank-options';
import type { BankAccountFormProps } from '../props/bank-account-form-props';
import type { ApiBankAccount } from '../types/api-bank-account';

export const BankAccountForm: FC<BankAccountFormProps> = ({ onCreated }) => {
    const { sendRequest } = useSendRequest();
    const { data: cuentasData, isLoading: isLoadingCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);

    const [banco, setBanco] = useState<ApiBankAccount['banco']>('bancolombia');
    const [numeroCuenta, setNumeroCuenta] = useState('');
    const [tipoCuenta, setTipoCuenta] = useState<ApiBankAccount['tipo_cuenta']>('ahorros');
    const [alias, setAlias] = useState('');
    const [planCuentaId, setPlanCuentaId] = useState('');
    const [planCuentaPuenteId, setPlanCuentaPuenteId] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const opcionesCuenta = cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));
    const formularioValido = numeroCuenta.trim() !== '' && planCuentaId !== '';

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ cuenta_bancaria: ApiBankAccount }>('/conciliacion/cuentas-bancarias', {
                method: 'POST',
                body: {
                    banco,
                    numero_cuenta: numeroCuenta.trim(),
                    tipo_cuenta: tipoCuenta,
                    alias: alias.trim() || null,
                    plan_cuenta_id: Number(planCuentaId),
                    plan_cuenta_puente_id: planCuentaPuenteId === '' ? null : Number(planCuentaPuenteId),
                },
            });

            onCreated(respuesta.cuenta_bancaria);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-5">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="banco">Banco</Label>
                        <Select id="banco" options={BANK_OPTIONS} defaultValue={banco} onChange={(valor) => setBanco(valor as ApiBankAccount['banco'])} />
                    </div>

                    <div>
                        <Label htmlFor="numero-cuenta">Número de cuenta</Label>
                        <Input
                            id="numero-cuenta"
                            required
                            startIcon={<Hash className="size-4" />}
                            value={numeroCuenta}
                            onChange={(event) => setNumeroCuenta(event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="tipo-cuenta">Tipo de cuenta</Label>
                        <Select
                            id="tipo-cuenta"
                            options={BANK_ACCOUNT_TYPE_OPTIONS}
                            defaultValue={tipoCuenta}
                            onChange={(valor) => setTipoCuenta(valor as ApiBankAccount['tipo_cuenta'])}
                        />
                    </div>

                    <div>
                        <Label htmlFor="alias">Alias (opcional)</Label>
                        <Input id="alias" placeholder="Cuenta nómina, Cuenta operativa…" value={alias} onChange={(event) => setAlias(event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="plan-cuenta">Cuenta contable (PUC)</Label>
                        <Select
                            id="plan-cuenta"
                            options={opcionesCuenta}
                            placeholder={isLoadingCuentas ? 'Cargando cuentas…' : 'Selecciona la cuenta'}
                            onChange={setPlanCuentaId}
                        />
                    </div>

                    <div>
                        <Label htmlFor="plan-cuenta-puente">Cuenta puente (por identificar, opcional)</Label>
                        <Select
                            id="plan-cuenta-puente"
                            options={opcionesCuenta}
                            placeholder={isLoadingCuentas ? 'Cargando cuentas…' : 'Selecciona la cuenta'}
                            placeholderSelectable
                            onChange={setPlanCuentaPuenteId}
                        />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !formularioValido}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                    >
                        {isSubmitting ? 'Creando…' : 'Crear cuenta bancaria'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
