import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import type { ApiSupplier } from '../types/api-supplier';
import type { ApiThirdParty } from '../types/api-third-party';
import type { PersonType } from '../types/person-type';
import type { ThirdPartyRole } from '../types/third-party-role';
import { buildThirdPartyCreateEndpoint, buildThirdPartyUpdateEndpoint } from '../utils/build-third-party-endpoint';

type UseThirdPartySubmitOptions = {
    thirdPartyId?: number;
    tipoPersona: PersonType;
};

/** Alta (proveedor u otro) o edición de un tercero; el endpoint depende del tipo de persona. */
export function useThirdPartySubmit({ thirdPartyId, tipoPersona }: UseThirdPartySubmitOptions) {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const submit = async (role: ThirdPartyRole, body: object) => {
        setError(null);
        setIsSubmitting(true);

        try {
            let mensaje = 'Tercero actualizado correctamente.';

            if (thirdPartyId !== undefined) {
                await sendRequest(buildThirdPartyUpdateEndpoint(thirdPartyId, tipoPersona), { method: 'PATCH', body });
            } else if (role === 'proveedor') {
                const respuesta = await sendRequest<{ proveedor: ApiSupplier }>(buildThirdPartyCreateEndpoint(role, tipoPersona), {
                    method: 'POST',
                    body,
                });

                mensaje = respuesta.proveedor.tercero_reutilizado
                    ? `Proveedor creado. Ya existía un tercero con ese documento (${respuesta.proveedor.tercero.nombre}); se usaron sus datos existentes.`
                    : 'Proveedor creado correctamente.';
            } else {
                const respuesta = await sendRequest<{ tercero: ApiThirdParty }>(buildThirdPartyCreateEndpoint(role, tipoPersona), {
                    method: 'POST',
                    body,
                });

                mensaje = respuesta.tercero.tercero_reutilizado
                    ? `Tercero creado. Ya existía un registro con ese documento (${respuesta.tercero.nombre}); se usaron sus datos existentes.`
                    : 'Tercero creado correctamente.';
            }

            navigate('/terceros', { replace: true, state: { message: mensaje } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return { submit, error, isSubmitting };
}
