import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { NATURAL_DOCUMENT_TYPE_OPTIONS } from '../constants/natural-document-type-options';
import { useThirdPartyContactFields } from '../hooks/use-third-party-contact-fields';
import { useThirdPartySubmit } from '../hooks/use-third-party-submit';
import type { NaturalThirdPartyFormProps } from '../props/natural-third-party-form-props';
import type { NaturalDocumentType } from '../types/natural-document-type';
import type { ThirdPartyRole } from '../types/third-party-role';
import { buildContactPayload } from '../utils/build-contact-payload';
import { ThirdPartyContactFields } from './third-party-contact-fields';
import { ThirdPartyFormFooter } from './third-party-form-footer';
import { ThirdPartyFormHeader } from './third-party-form-header';

export const NaturalThirdPartyForm: FC<NaturalThirdPartyFormProps> = ({ thirdPartyId, initialData }) => {
    const isEditMode = thirdPartyId !== undefined;

    const [rol, setRol] = useState<ThirdPartyRole>('otro');
    const [tipoDocumento, setTipoDocumento] = useState<NaturalDocumentType>(initialData?.tipo_documento ?? 'cc');
    const [numeroDocumento, setNumeroDocumento] = useState(initialData?.numero_documento ?? '');
    const [nombres, setNombres] = useState(initialData?.nombres ?? '');
    const [apellidos, setApellidos] = useState(initialData?.apellidos ?? '');
    const [activo, setActivo] = useState<boolean>(initialData?.activo ?? true);

    const { values, setValue } = useThirdPartyContactFields(initialData);
    const { submit, error, isSubmitting } = useThirdPartySubmit({ thirdPartyId, tipoPersona: 'natural' });

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        await submit(rol, {
            tipo_documento: tipoDocumento,
            numero_documento: numeroDocumento,
            nombres,
            apellidos,
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
                    <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Identificación · Persona natural</p>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <Label htmlFor="nombres">Nombres</Label>
                            <Input id="nombres" name="nombres" required value={nombres} onChange={(event) => setNombres(event.target.value)} />
                        </div>

                        <div>
                            <Label htmlFor="apellidos">Apellidos</Label>
                            <Input id="apellidos" name="apellidos" required value={apellidos} onChange={(event) => setApellidos(event.target.value)} />
                        </div>

                        <div>
                            <Label htmlFor="tipo-documento">Tipo de documento</Label>
                            <Select
                                options={NATURAL_DOCUMENT_TYPE_OPTIONS}
                                defaultValue={tipoDocumento}
                                onChange={(value) => setTipoDocumento(value as NaturalDocumentType)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="numero-documento">Número de documento</Label>
                            <Input
                                id="numero-documento"
                                name="numero-documento"
                                required
                                value={numeroDocumento}
                                onChange={(event) => setNumeroDocumento(event.target.value)}
                            />
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
