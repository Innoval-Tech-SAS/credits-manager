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

/** En edición el backend solo acepta el nombre y lo que la cuenta exige: código, naturaleza, tipo, nivel y padre quedan fijos. */
export const AccountForm: FC<AccountFormProps> = ({ cuentas, accountId, initialData, onSaved }) => {
    const { sendRequest } = useSendRequest();
    const isEditMode = accountId !== undefined;

    const [codigo, setCodigo] = useState(initialData?.codigo ?? '');
    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [naturaleza, setNaturaleza] = useState<ApiAccount['naturaleza']>(initialData?.naturaleza ?? 'debito');
    const [nivel, setNivel] = useState(String(initialData?.nivel ?? 1));
    const [cuentaPadreId, setCuentaPadreId] = useState(initialData?.cuenta_padre_id ? String(initialData.cuenta_padre_id) : '');
    const [tipo, setTipo] = useState<ApiAccount['tipo']>(initialData?.tipo ?? 'activo');
    const [permiteMovimiento, setPermiteMovimiento] = useState(initialData?.permite_movimiento ?? true);
    const [exigeTercero, setExigeTercero] = useState(initialData?.exige_tercero ?? false);
    const [exigeDocumentoCruce, setExigeDocumentoCruce] = useState(initialData?.exige_documento_cruce ?? false);
    const [exigeVencimiento, setExigeVencimiento] = useState(initialData?.exige_vencimiento ?? false);
    const [exigeCentroCosto, setExigeCentroCosto] = useState(initialData?.exige_centro_costo ?? false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const cuentaPadre = cuentas.find((cuenta) => String(cuenta.id) === cuentaPadreId);
    const nombreCuentaPadre = cuentaPadre ? `${cuentaPadre.codigo} - ${cuentaPadre.nombre}` : 'Ninguna';

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        const requisitos = {
            permite_movimiento: permiteMovimiento,
            exige_tercero: exigeTercero,
            exige_documento_cruce: exigeDocumentoCruce,
            exige_vencimiento: exigeVencimiento,
            exige_centro_costo: exigeCentroCosto,
        };

        try {
            const respuesta = isEditMode
                ? await sendRequest<{ cuenta: ApiAccount }>(`/contabilidad/plan-cuentas/${accountId}`, {
                      method: 'PATCH',
                      body: { nombre, ...requisitos },
                  })
                : await sendRequest<{ cuenta: ApiAccount }>('/contabilidad/plan-cuentas', {
                      method: 'POST',
                      body: {
                          codigo,
                          nombre,
                          naturaleza,
                          nivel: Number(nivel),
                          cuenta_padre_id: cuentaPadreId ? Number(cuentaPadreId) : null,
                          tipo,
                          ...requisitos,
                      },
                  });

            onSaved(respuesta.cuenta);
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
                        <Input id="codigo" name="codigo" required disabled={isEditMode} value={codigo} onChange={(event) => setCodigo(event.target.value)} />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>
                </div>

                {isEditMode ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <div>
                            <Label htmlFor="naturaleza">Naturaleza</Label>
                            <Input id="naturaleza" disabled value={ACCOUNT_NATURE_OPTIONS.find((opcion) => opcion.value === naturaleza)?.label ?? naturaleza} />
                        </div>

                        <div>
                            <Label htmlFor="tipo">Tipo</Label>
                            <Input id="tipo" disabled value={ACCOUNT_TYPE_OPTIONS.find((opcion) => opcion.value === tipo)?.label ?? tipo} />
                        </div>

                        <div>
                            <Label htmlFor="nivel">Nivel</Label>
                            <Input id="nivel" disabled value={nivel} />
                        </div>

                        <div>
                            <Label htmlFor="cuenta-padre">Cuenta padre</Label>
                            <Input id="cuenta-padre" disabled value={nombreCuentaPadre} />
                        </div>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-4">
                        <div>
                            <Label htmlFor="naturaleza">Naturaleza</Label>
                            <Select
                                id="naturaleza"
                                options={ACCOUNT_NATURE_OPTIONS}
                                defaultValue={naturaleza}
                                onChange={(value) => setNaturaleza(value as ApiAccount['naturaleza'])}
                            />
                        </div>

                        <div>
                            <Label htmlFor="tipo">Tipo</Label>
                            <Select
                                id="tipo"
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
                                id="cuenta-padre"
                                options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                                placeholder="Ninguna"
                                defaultValue={cuentaPadreId}
                                onChange={setCuentaPadreId}
                            />
                        </div>
                    </div>
                )}

                <Checkbox
                    id="permite-movimiento"
                    label="Permite movimiento (cuenta de detalle)"
                    checked={permiteMovimiento}
                    onChange={setPermiteMovimiento}
                />

                <div>
                    <p className="mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-400">Qué exige esta cuenta al usarse en un comprobante</p>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Checkbox id="exige-tercero" label="Exige tercero" checked={exigeTercero} onChange={setExigeTercero} />
                        <Checkbox id="exige-documento-cruce" label="Exige documento cruce" checked={exigeDocumentoCruce} onChange={setExigeDocumentoCruce} />
                        <Checkbox id="exige-vencimiento" label="Exige vencimiento" checked={exigeVencimiento} onChange={setExigeVencimiento} />
                        <Checkbox id="exige-centro-costo" label="Exige centro de costo" checked={exigeCentroCosto} onChange={setExigeCentroCosto} />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? (isEditMode ? 'Actualizando…' : 'Creando…') : isEditMode ? 'Actualizar cuenta' : 'Crear cuenta'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
