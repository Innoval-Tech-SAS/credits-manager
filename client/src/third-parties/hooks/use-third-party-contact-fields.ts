import { useCallback, useState } from 'react';

import type { ApiThirdParty } from '../types/api-third-party';
import type { ThirdPartyContactValues } from '../types/third-party-contact-values';

/** Estado compartido por los formularios de persona natural y jurídica para ubicación, contacto y datos fiscales. */
export function useThirdPartyContactFields(initialData?: ApiThirdParty) {
    const [values, setValues] = useState<ThirdPartyContactValues>({
        direccion: initialData?.direccion ?? '',
        barrio: initialData?.barrio ?? '',
        ciudad: initialData?.ciudad ?? '',
        celular: initialData?.celular ?? '',
        telefonoFijo: initialData?.telefono_fijo ?? '',
        correo: initialData?.correo ?? '',
        regimenTributario: initialData?.regimen_tributario ?? '',
        actividadEconomicaId: initialData?.actividad_economica_id ? String(initialData.actividad_economica_id) : '',
        autorretenedor: initialData?.autorretenedor ?? false,
        responsableIva: initialData?.responsable_iva ?? false,
    });

    const setValue = useCallback(<K extends keyof ThirdPartyContactValues>(key: K, value: ThirdPartyContactValues[K]) => {
        setValues((current) => ({ ...current, [key]: value }));
    }, []);

    return { values, setValue };
}
