import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useResource } from '../../shared/hooks/use-resource';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { GoalSettingsForm } from '../components/goal-settings-form';
import type { ApiGoalSettings } from '../types/api-goal-settings';

export const GoalSettingsPage: FC = () => {
    const navigate = useNavigate();
    const [guardado, setGuardado] = useState(false);

    const { data, error } = useResource<{ parametros: ApiGoalSettings }>('/metas/configuracion');

    if (error) {
        return (
            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                {error}
            </div>
        );
    }

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Parámetros de metas" />

            <div className="mb-6">
                <Button size="sm" variant="outline" onClick={() => navigate('/metas')}>
                    Volver a metas
                </Button>
            </div>

            {guardado && <Alert variant="success" title="Parámetros guardados" message="Los cambios aplican a los períodos abiertos. Los períodos cerrados no cambian." onDismiss={() => setGuardado(false)} />}

            <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
                {!data ? (
                    <div className="flex justify-center py-12">
                        <Spinner size="lg" />
                    </div>
                ) : (
                    <GoalSettingsForm configuracion={data.parametros} onSaved={() => setGuardado(true)} />
                )}
            </section>
        </div>
    );
};
