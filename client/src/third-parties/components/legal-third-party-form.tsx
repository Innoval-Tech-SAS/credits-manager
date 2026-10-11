import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { useThirdPartyContactFields } from '../hooks/use-third-party-contact-fields';
import { useThirdPartySubmit } from '../hooks/use-third-party-submit';
import type { LegalThirdPartyFormProps } from '../props/legal-third-party-form-props';
import type { ThirdPartyRole } from '../types/third-party-role';
import { buildContactPayload } from '../utils/build-contact-payload';
import { calculateVerificationDigit } from '../../shared/utils/calculate-verification-digit';
import { ThirdPartyContactFields } from './third-party-contact-fields';
import { ThirdPartyFormFooter } from './third-party-form-footer';
import { ThirdPartyFormHeader } from './third-party-form-header';

export const LegalThirdPartyForm: FC<LegalThirdPartyFormProps> = ({ thirdPartyId, initialData }) => {
    const isEditMode = thirdPartyId !== undefined;

    const [rol, setRol] = useState<ThirdPartyRole>('otro');
    const [razonSocial, setRazonSocial] = useState(initialData?.razon_social ?? '');
    const [nit, setNit] = useState(initialData?.numero_documento ?? '');
    const [representanteLegal, setRepresentanteLegal] = useState(initialData?.representante_legal ?? '');
    const [activo, setActivo] = useState<boolean>(initialData?.activo ?? true);

    const { values, setValue } = useThirdPartyContactFields(initialData);
    const { submit, error, isSubmitting } = useThirdPartySubmit({ thirdPartyId, tipoPersona: 'juridica' });

    const digitoVerificacion = calculateVerificationDigit(nit);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        await submit(rol, {
            numero_documento: nit,
            digito_verificacion: digitoVerificacion,
            razon_social: razonSocial,
            representante_legal: representanteLegal,
            ...buildContactPayload(values),
            ...(isEditMode ? { activo } : {}),
        });
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div>
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Identificación · Persona jurídica</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="razon-social">Razón social</Label>
                            <Input id="razon-social" name="razon-social" required value={razonSocial} onChange={(event) => setRazonSocial(event.target.value)} />
                        </div>

                        <div>
                            <Label htmlFor="representante-legal">Representante legal</Label>
                            <Input
                                id="representante-legal"
                                name="representante-legal"
                                required
                                value={representanteLegal}
                                onChange={(event) => setRepresentanteLegal(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="nit">NIT (sin dígito de verificación)</Label>
                            <Input
                                id="nit"
                                name="nit"
                                required
                                minLength={6}
                                maxLength={15}
                                value={nit}
                                onChange={(event) => setNit(event.target.value.replace(/\D/g, ''))}
                            />
                        </div>

                        <div>
                            <Label htmlFor="digito-verificacion">Dígito de verificación</Label>
                            <Input id="digito-verificacion" name="digito-verificacion" disabled value={digitoVerificacion} hint="Se calcula a partir del NIT." />
                        </div>

                        <ThirdPartyFormHeader
                            isEditMode={isEditMode}
                            role={rol}
                            onRoleChange={setRol}
                            activo={activo}
                            onActivoChange={setActivo}
                        />
                    </div>
                </div>

                <ThirdPartyContactFields values={values} onChange={setValue} />

                <ThirdPartyFormFooter isEditMode={isEditMode} isSubmitting={isSubmitting} />
            </div>
        </form>
    );
};
