import type { ThirdPartyDocumentType } from './third-party-document-type';

/** Documentos de una persona natural: el NIT es exclusivo de las personas jurídicas. */
export type NaturalDocumentType = Exclude<ThirdPartyDocumentType, 'nit'>;
