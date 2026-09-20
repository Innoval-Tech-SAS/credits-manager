import type { ApiClientDocument } from '../types/api-client-document';

export type ClientDocumentsProps = {
    documentos: ApiClientDocument[];
    canUpload: boolean;
    isUploading: boolean;
    uploadError: string | null;
    onUpload: (archivo: File) => void;
};
