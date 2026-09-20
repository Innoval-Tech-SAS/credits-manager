import type { FC } from 'react';

import { API_ROOT } from '../../shared/constants/api';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { ACCEPTED_DOCUMENT_TYPES } from '../constants/accepted-document-types';
import type { ClientDocumentsProps } from '../props/client-documents-props';

export const ClientDocuments: FC<ClientDocumentsProps> = ({ documentos, canUpload, isUploading, uploadError, onUpload }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <h2 className="mb-4 text-lg font-semibold text-gray-800 dark:text-white/90">Documentos</h2>

        {canUpload && (
            <div className="mb-4">
                {uploadError && (
                    <div className="mb-3 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {uploadError}
                    </div>
                )}
                <Dropzone accept={ACCEPTED_DOCUMENT_TYPES} hint={isUploading ? 'Subiendo…' : 'PDF, PNG, JPG o WEBP'} onFileAccepted={onUpload} />
            </div>
        )}

        {documentos.length === 0 ? (
            <p className="text-sm text-gray-500 dark:text-gray-400">Este cliente aún no tiene documentos cargados.</p>
        ) : (
            <ul className="divide-y divide-gray-100 dark:divide-gray-800">
                {documentos.map((documento) => (
                    <li key={documento.id} className="flex items-center justify-between py-3">
                        <a
                            href={`${API_ROOT}/${documento.ruta}`}
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-brand-600 hover:underline dark:text-brand-400"
                        >
                            {documento.nombre_original}
                        </a>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            {documento.creado_en ? new Date(documento.creado_en).toLocaleDateString('es-CO') : '—'}
                        </span>
                    </li>
                ))}
            </ul>
        )}
    </div>
);
