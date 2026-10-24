import type { NaturalDocumentType } from './natural-document-type';
import type { PersonType } from './person-type';
import type { ThirdPartyDocumentType } from './third-party-document-type';

type ApiThirdPartyBase = {
    id: number;
    tipo_documento: ThirdPartyDocumentType;
    numero_documento: string;
    /** Nombres y apellidos o razón social: se calcula en el servidor, nunca se envía. */
    nombre: string;
    direccion: string | null;
    barrio: string | null;
    ciudad: string | null;
    celular: string | null;
    telefono_fijo: string | null;
    correo: string | null;
    regimen_tributario: string | null;
    tipo_persona: PersonType;
    autorretenedor: boolean;
    responsable_iva: boolean;
    actividad_economica_id: number | null;
    activo: boolean;
    /** A qué extensión pertenece este tercero — null si aún no tiene ninguna (recién creado desde el catálogo genérico). */
    tipo: 'cliente' | 'proveedor' | 'otro' | null;
    /** Solo presente en la respuesta de las altas: si el tercero ya existía (por documento) y se reutilizó. */
    tercero_reutilizado?: boolean;
};

export type ApiNaturalThirdParty = ApiThirdPartyBase & {
    tipo_persona: 'natural';
    tipo_documento: NaturalDocumentType;
    nombres: string;
    /** Nulo solo en terceros anteriores a la separación de nombres y apellidos con una única palabra. */
    apellidos: string | null;
    razon_social: null;
    digito_verificacion: null;
    representante_legal: null;
};

export type ApiLegalThirdParty = ApiThirdPartyBase & {
    tipo_persona: 'juridica';
    tipo_documento: 'nit';
    razon_social: string;
    digito_verificacion: string;
    /** Nulo solo en terceros anteriores a que se pidiera el representante legal. */
    representante_legal: string | null;
    nombres: null;
    apellidos: null;
};

/** Raw shape returned by `GET /terceros` (rows), `GET /terceros/{id}`, `POST /terceros/personas-naturales|personas-juridicas` y `PATCH /terceros/personas-naturales|personas-juridicas/{id}` — también anidado como `cliente.tercero` y `proveedor.tercero`. */
export type ApiThirdParty = ApiNaturalThirdParty | ApiLegalThirdParty;
