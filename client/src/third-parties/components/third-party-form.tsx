import type { FC } from 'react';
import { useState } from 'react';

import type { ThirdPartyFormProps } from '../props/third-party-form-props';
import type { PersonType } from '../types/person-type';
import { LegalThirdPartyForm } from './legal-third-party-form';
import { NaturalThirdPartyForm } from './natural-third-party-form';
import { PersonTypeSelector } from './person-type-selector';

/** En el alta el usuario elige primero el tipo de persona (sin valor por defecto); al editar, el tipo ya está fijado. */
export const ThirdPartyForm: FC<ThirdPartyFormProps> = ({ thirdPartyId, initialData }) => {
    const [tipoPersona, setTipoPersona] = useState<PersonType | null>(null);

    if (initialData?.tipo_persona === 'natural') {
        return <NaturalThirdPartyForm thirdPartyId={thirdPartyId} initialData={initialData} />;
    }

    if (initialData?.tipo_persona === 'juridica') {
        return <LegalThirdPartyForm thirdPartyId={thirdPartyId} initialData={initialData} />;
    }

    return (
        <div className="space-y-6">
            <PersonTypeSelector value={tipoPersona} onChange={setTipoPersona} />

            {tipoPersona === null && (
                <p className="text-sm text-gray-500 dark:text-gray-400">Elige el tipo de persona para continuar con el formulario.</p>
            )}

            {tipoPersona === 'natural' && <NaturalThirdPartyForm />}
            {tipoPersona === 'juridica' && <LegalThirdPartyForm />}
        </div>
    );
};
