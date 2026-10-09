import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import { ACCOUNT_NATURE_OPTIONS } from '../constants/account-nature-options';
import { ACCOUNT_TYPE_OPTIONS } from '../constants/account-type-options';
import type { AccountFormProps } from '../props/account-form-props';
import type { ApiAccount } from '../types/api-account';

export const AccountForm: FC<AccountFormProps> = ({ cuentas, onCreated }) => {
    const { sendRequest } = useSendRequest();

    const [codigo, setCodigo] = useState('');
    const [nombre, setNombre] = useState('');
    const [naturaleza, setNaturaleza] = useState<ApiAccount['naturaleza']>('debito');
    const [nivel, setNivel] = useState('1');
    const [cuentaPadreId, setCuentaPadreId] = useState('');
    const [tipo, setTipo] = useState<ApiAccount['tipo']>('activo');
    const [permiteMovimiento, setPermiteMovimiento] = useState(true);
    const [exigeTercero, setExigeTercero] = useState(false);
    const [exigeDocumentoCruce, setExigeDocumentoCruce] = useState(false);
    const [exigeVencimiento, setExigeVencimiento] = useState(false);
    const [exigeCentroCosto, setExigeCentroCosto] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = await sendRequest<{ cuenta: ApiAccount }>('/contabilidad/plan-cuentas', {
                method: 'POST',
                body: {
                    codigo,
                    nombre,
                    naturaleza,
                    nivel: Number(nivel),
                    cuenta_padre_id: cuentaPadreId ? Number(cuentaPadreId) : null,
                    tipo,
                    permite_movimiento: permiteMovimiento,
                    exige_tercero: exigeTercero,
                    exige_documento_cruce: exigeDocumentoCruce,
                    exige_vencimiento: exigeVencimiento,
                    exige_centro_costo: exigeCentroCosto,
                },
            });

            onCreated(respuesta.cuenta);
            setCodigo('');
            setNombre('');
            setCuentaPadreId('');
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

                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="codigo">Código</Label>
                        <Input id="codigo" name="codigo" required value={codigo} onChange={(event) => setCodigo(event.target.value)} />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                    <div>
                        <Label htmlFor="naturaleza">Naturaleza</Label>
                        <Select
                            options={ACCOUNT_NATURE_OPTIONS}
                            defaultValue={naturaleza}
                            onChange={(value) => setNaturaleza(value as ApiAccount['naturaleza'])}
                        />
                    </div>

                    <div>
                        <Label htmlFor="tipo">Tipo</Label>
                        <Select
                            options={ACCOUNT_TYPE_OPTIONS}
                            defaultValue={tipo}
                            onChange={(value) => setTipo(value as ApiAccount['tipo'])}
                        />
                    </div>

                    <div>
                        <Label htmlFor="nivel">Nivel</Label>
                        <Input id="nivel" name="nivel" type="number" min="1" value={nivel} onChange={(event) => setNivel(event.target.value)} />
                    </div>

                    <div>
                        <Label htmlFor="cuenta-padre">Cuenta padre</Label>
                        <Select
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Ninguna"
                            defaultValue={cuentaPadreId}
                            onChange={setCuentaPadreId}
                        />
                    </div>
                </div>

                <Checkbox
                    label="Permite movimiento (cuenta de detalle)"
                    checked={permiteMovimiento}
                    onChange={setPermiteMovimiento}
                />

                <div>
                    <Label>Qué exige esta cuenta al usarse en un comprobante</Label>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Checkbox label="Exige tercero" checked={exigeTercero} onChange={setExigeTercero} />
                        <Checkbox label="Exige documento cruce" checked={exigeDocumentoCruce} onChange={setExigeDocumentoCruce} />
                        <Checkbox label="Exige vencimiento" checked={exigeVencimiento} onChange={setExigeVencimiento} />
                        <Checkbox label="Exige centro de costo" checked={exigeCentroCosto} onChange={setExigeCentroCosto} />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Creando…' : 'Crear cuenta'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
