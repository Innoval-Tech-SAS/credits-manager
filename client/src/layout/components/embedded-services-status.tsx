import type { FC } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { SkeletonBar } from '@ui/skeleton/components/skeleton-bar';
import { ServicesStatusCard } from '../../monitoring/components/services-status-card';
import { useSystemHealth } from '../../monitoring/hooks/use-system-health';

/** Teaser de servicios en el home: mismo componente de Salud del sistema, con su propia carga (Reverb + fallback). */
export const EmbeddedServicesStatus: FC = () => {
    const { health, error } = useSystemHealth();

    if (error && !health) {
        return <Alert variant="danger" title="No se pudo cargar el estado de los servicios" message={error} />;
    }

    if (!health) {
        return (
            <div className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
                <SkeletonBar className="mb-5 h-5 w-24" />
                <div className="flex flex-col gap-4">
                    {Array.from({ length: 4 }, (_, indice) => (
                        <SkeletonBar key={indice} className="h-10 w-full" />
                    ))}
                </div>
            </div>
        );
    }

    return <ServicesStatusCard servicios={health.servicios} />;
};
