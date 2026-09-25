import type { PersonType } from '../types/person-type';

export const PERSON_TYPE_LABELS: Record<PersonType, string> = {
    natural: 'Persona natural',
    juridica: 'Persona jurídica',
};

export const PERSON_TYPE_DESCRIPTIONS: Record<PersonType, string> = {
    natural: 'Nombres y apellidos, con cédula o pasaporte.',
    juridica: 'Razón social y NIT con dígito de verificación.',
};
