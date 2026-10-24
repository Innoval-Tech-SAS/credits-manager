import type { ApiBusinessDocumentType } from '../types/api-business-document-type';

export type BusinessDocumentTypeFormProps = {
    tipo: ApiBusinessDocumentType;
    onSaved: (tipo: ApiBusinessDocumentType) => void;
};
