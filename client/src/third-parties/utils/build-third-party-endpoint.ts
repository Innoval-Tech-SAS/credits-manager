import type { PersonType } from '../types/person-type';
import type { ThirdPartyRole } from '../types/third-party-role';

const PERSON_TYPE_PATHS: Record<PersonType, string> = {
    natural: 'personas-naturales',
    juridica: 'personas-juridicas',
};

const ROLE_COLLECTIONS: Record<ThirdPartyRole, string> = {
    proveedor: 'proveedores',
    otro: 'terceros',
};

export function buildThirdPartyCreateEndpoint(role: ThirdPartyRole, tipoPersona: PersonType): string {
    return `/${ROLE_COLLECTIONS[role]}/${PERSON_TYPE_PATHS[tipoPersona]}`;
}

export function buildThirdPartyUpdateEndpoint(thirdPartyId: number, tipoPersona: PersonType): string {
    return `/terceros/${PERSON_TYPE_PATHS[tipoPersona]}/${thirdPartyId}`;
}
