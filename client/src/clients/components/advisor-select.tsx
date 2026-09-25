import type { FC } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { Select } from '@ui/forms/components/select';
import type { AdvisorSelectProps } from '../props/advisor-select-props';
import type { ApiAdvisor } from '../types/api-advisor';

/** Asesores (y administradores) activos de la empresa a quienes se puede asignar un cliente. */
export const AdvisorSelect: FC<AdvisorSelectProps> = ({ value, onChange, excludeId }) => {
    const { data, error } = useResource<{ asesores: ApiAdvisor[] }>('/clientes/asesores-asignables');

    if (error) {
        return <p className="text-sm text-error-600 dark:text-error-400">{error}</p>;
    }

    const options = (data?.asesores ?? [])
        .filter((advisor) => advisor.id !== excludeId)
        .map((advisor) => ({ value: String(advisor.id), label: advisor.nombre }));

    return (
        <Select
            key={data ? 'loaded' : 'loading'}
            options={options}
            defaultValue={value !== null ? String(value) : undefined}
            placeholder={data ? 'Selecciona un asesor…' : 'Cargando asesores…'}
            onChange={(selected) => onChange(selected ? Number(selected) : null)}
        />
    );
};
