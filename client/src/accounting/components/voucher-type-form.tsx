import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { Spinner } from '@ui/spinner/components/spinner';
import type { VoucherTypeFormProps } from '../props/voucher-type-form-props';
import type { ApiVoucherType } from '../types/api-voucher-type';

export const VoucherTypeForm: FC<VoucherTypeFormProps> = ({ voucherTypeId, initialData, onSaved }) => {
    const { sendRequest } = useSendRequest();
    const isEditMode = voucherTypeId !== undefined;

    const [codigo, setCodigo] = useState(initialData?.codigo ?? '');
    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [activo, setActivo] = useState(initialData?.activo ?? true);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            const respuesta = isEditMode
                ? await sendRequest<{ tipo: ApiVoucherType }>(`/contabilidad/tipos-comprobante/${voucherTypeId}`, {
                      method: 'PATCH',
                      body: { codigo, nombre, activo },
                  })
                : await sendRequest<{ tipo: ApiVoucherType }>('/contabilidad/tipos-comprobante', {
                      method: 'POST',
                      body: { codigo, nombre },
                  });

            onSaved(respuesta.tipo);

            if (!isEditMode) {
                setCodigo('');
                setNombre('');
            }
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
                        <Input
                            id="codigo"
                            name="codigo"
                            placeholder="CI, CE, RC…"
                            required
                            value={codigo}
                            onChange={(event) => setCodigo(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input id="nombre" name="nombre" required value={nombre} onChange={(event) => setNombre(event.target.value)} />
                    </div>
                </div>

                {isEditMode && (
                    <div className="flex flex-wrap gap-8">
                        <ToggleSwitchInput id="activo" label="Activo" checked={activo} onChange={setActivo} />
                    </div>
                )}

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button type="submit" disabled={isSubmitting} startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}>
                        {isSubmitting ? 'Guardando…' : isEditMode ? 'Actualizar tipo de comprobante' : 'Crear tipo de comprobante'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
