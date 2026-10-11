import type { FC } from 'react';
import { useNavigate, useParams } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { UvtValueForm } from '../components/uvt-value-form';
import type { ApiUvtValue } from '../types/api-uvt-value';

export const EditUvtValuePage: FC = () => {
    const navigate = useNavigate();
    const { id } = useParams<{ id: string }>();

    const { data, error } = useResource<{ valores_uvt: ApiUvtValue[] }>('/gastos/valores-uvt');
    const valorUvt = data?.valores_uvt.find((fila) => fila.id === Number(id)) ?? null;

    const handleSaved = () => {
        navigate('/gastos/valores-uvt', { replace: true, state: { message: 'Valor UVT actualizado correctamente.' } });
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Editar valor UVT" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 md:p-8 dark:border-gray-800 dark:bg-white/3">
                {error && (
                    <div className="mb-6 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                {!error && !valorUvt && (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                )}

                {valorUvt && <UvtValueForm valorUvtId={valorUvt.id} initialData={valorUvt} onSaved={handleSaved} />}
            </div>
        </div>
    );
};
