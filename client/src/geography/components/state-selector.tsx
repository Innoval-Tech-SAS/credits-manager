import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import type { StateSelectorProps } from '../props/state-selector-props';
import type { ApiCountry } from '../types/api-country';
import type { ApiState } from '../types/api-state';

/** Dos campos en cascada (país → estado) para colocar dentro de un grid del formulario. */
export const StateSelector: FC<StateSelectorProps> = ({ onChange, initialCountryId = '', initialStateId = '' }) => {
    const { data: countriesData } = useResource<{ paises: ApiCountry[] }>('/geografia/paises');
    const countries = countriesData?.paises ?? [];

    const [pickedCountryId, setPickedCountryId] = useState(initialCountryId);
    const countryId = pickedCountryId || (countries.length === 1 ? String(countries[0].id) : '');

    const { data: statesData } = useResource<{ estados: ApiState[] }>(countryId ? `/geografia/estados?pais_id=${countryId}` : null);
    const states = statesData?.estados ?? [];

    const handleCountryChange = (value: string) => {
        setPickedCountryId(value);
        onChange('');
    };

    return (
        <>
            <div>
                <Label htmlFor="pais">País</Label>
                <Select
                    id="pais"
                    key={`pais-${countries.length}`}
                    options={countries.map((country) => ({ value: String(country.id), label: country.nombre }))}
                    placeholder="País"
                    defaultValue={countryId}
                    onChange={handleCountryChange}
                />
            </div>

            <div>
                <Label htmlFor="estado">Departamento / Estado</Label>
                <Select
                    id="estado"
                    key={`estado-${countryId}-${states.length}`}
                    options={states.map((state) => ({ value: String(state.id), label: state.nombre }))}
                    placeholder="Departamento"
                    defaultValue={countryId === initialCountryId ? initialStateId : ''}
                    onChange={onChange}
                />
            </div>
        </>
    );
};
