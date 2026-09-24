import type { FC } from 'react';
import { useDropzone } from 'react-dropzone';

import { Upload } from 'lucide-react';
import { ACCEPTED_IMAGE_TYPES } from '../constants/accepted-image-types';
import type { DropzoneProps } from '../props/dropzone-props';

export const Dropzone: FC<DropzoneProps> = ({ onFileAccepted, hint, accept = ACCEPTED_IMAGE_TYPES }) => {
    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        accept,
        multiple: false,
        onDrop: (acceptedFiles) => {
            const file = acceptedFiles[0];
            if (file) {
                onFileAccepted(file);
            }
        },
    });

    return (
        <div className="transition border border-gray-300 border-dashed cursor-pointer dark:hover:border-brand-500 dark:border-gray-700 rounded-xl hover:border-brand-500">
            <div
                {...getRootProps()}
                className={`relative rounded-xl border-dashed p-7 text-center ${
                    isDragActive
                        ? 'border-brand-500 bg-gray-100 dark:bg-gray-800'
                        : 'border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900'
                }`}
            >
                <input {...getInputProps()} />

                <div className="flex flex-col items-center">
                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <Upload className="size-6" />
                    </div>

                    <p className="mb-2 font-semibold text-gray-800 dark:text-white/90">
                        {isDragActive ? 'Suelta el archivo aquí' : 'Arrastra y suelta tu imagen aquí'}
                    </p>

                    <p className="mb-3 max-w-70 text-sm text-gray-500 dark:text-gray-400">
                        {hint ?? 'PNG, JPG, WEBP o SVG'}
                    </p>

                    <span className="text-sm font-medium text-brand-500 underline">Buscar archivo</span>
                </div>
            </div>
        </div>
    );
};
