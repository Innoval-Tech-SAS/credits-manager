import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import type { MunicipalitySelectorProps } from '../props/municipality-selector-props';
import type { ApiMunicipality } from '../types/api-municipality';
import { StateSelector } from './state-selector';

/** País → departamento → municipio del catálogo global. Ocupa una fila completa del formulario. */
export const MunicipalitySelector: FC<MunicipalitySelectorProps> = ({ onChange }) => {
    const [stateId, setStateId] = useState('');

    const { data } = useResource<{ municipios: ApiMunicipality[] }>(stateId ? `/geografia/municipios?estado_id=${stateId}` : null);
    const municipalities = data?.municipios ?? [];

    const handleStateChange = (value: string) => {
        setStateId(value);
        onChange('');
    };

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            <StateSelector onChange={handleStateChange} />

            <div>
                <Label htmlFor="municipio">Municipio</Label>
                <Select
                    key={`municipio-${stateId}-${municipalities.length}`}
                    options={municipalities.map((municipality) => ({ value: String(municipality.id), label: municipality.nombre }))}
                    placeholder="Municipio"
                    onChange={onChange}
                />
            </div>
        </div>
    );
};
