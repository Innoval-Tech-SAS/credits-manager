import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { calculateVerificationDigit } from '../../shared/utils/calculate-verification-digit';
import { Building2, IdCard, MapPin, Phone, Save } from 'lucide-react';
import type { EmployerFormProps } from '../props/employer-form-props';

export const EmployerForm: FC<EmployerFormProps> = ({ employerId, initialData }) => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const isEditMode = employerId !== undefined;

    const [nombre, setNombre] = useState(initialData?.nombre ?? '');
    const [nit, setNit] = useState(initialData?.nit?.replace(/\D/g, '') ?? '');
    const digitoVerificacion = calculateVerificationDigit(nit);
    const [direccion, setDireccion] = useState(initialData?.direccion ?? '');
    const [telefono, setTelefono] = useState(initialData?.telefono ?? '');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        const body = {
            nombre,
            nit: nit || null,
            digito_verificacion: digitoVerificacion || null,
            direccion: direccion || null,
            telefono: telefono || null,
        };

        try {
            if (isEditMode) {
                await sendRequest(`/empleadores/${employerId}`, { method: 'PATCH', body });
            } else {
                await sendRequest('/empleadores', { method: 'POST', body });
            }

            navigate('/empleadores', {
                replace: true,
                state: { message: isEditMode ? 'Empleador actualizado correctamente.' : 'Empleador creado correctamente.' },
            });
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
                    <div className="md:col-span-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            name="nombre"
                            required
                            startIcon={<Building2 className="size-4" />}
                            value={nombre}
                            onChange={(event) => setNombre(event.target.value)}
                        />
                    </div>

                    <div>
                        <Label htmlFor="nit">NIT</Label>
                        <Input
                            id="nit"
                            name="nit"
                            maxLength={15}
                            startIcon={<IdCard className="size-4" />}
                            value={nit}
                            onChange={(event) => setNit(event.target.value.replace(/\D/g, ''))}
                        />
                    </div>

                    <div>
                        <Label htmlFor="digito-verificacion">Dígito de verificación</Label>
                        <Input
                            id="digito-verificacion"
                            name="digito-verificacion"
                            disabled
                            value={digitoVerificacion}
                            hint="Se calcula a partir del NIT."
                        />
                    </div>

                    <div>
                        <Label htmlFor="telefono">Teléfono</Label>
                        <Input
                            id="telefono"
                            name="telefono"
                            startIcon={<Phone className="size-4" />}
                            value={telefono}
                            onChange={(event) => setTelefono(event.target.value)}
                        />
                    </div>

                    <div className="md:col-span-2">
                        <Label htmlFor="direccion">Dirección</Label>
                        <Input
                            id="direccion"
                            name="direccion"
                            startIcon={<MapPin className="size-4" />}
                            value={direccion}
                            onChange={(event) => setDireccion(event.target.value)}
                        />
                    </div>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting
                            ? isEditMode
                                ? 'Actualizando…'
                                : 'Creando…'
                            : isEditMode
                              ? 'Actualizar empleador'
                              : 'Crear empleador'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
