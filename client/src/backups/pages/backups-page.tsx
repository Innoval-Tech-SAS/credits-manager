import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Alert } from '@ui/alert/components/alert';
import { DatabaseBackup, Building2 } from 'lucide-react';
import { useAuth } from '../../auth/hooks/use-auth';
import { BackupsStatusCard } from '../../monitoring/components/backups-status-card';
import type { ApiBackupsStatus } from '../../monitoring/types/api-backups-status';
import { useResource } from '../../shared/hooks/use-resource';
import { BackupActionCard } from '../components/backup-action-card';
import { BackupDownloadCard } from '../components/backup-download-card';

/** Superadmin: base completa de todas las empresas, más el detalle operativo (historial, estado, respaldo manual). Administrador de empresa: solo los datos de la suya. */
export const BackupsPage: FC = () => {
    const { user } = useAuth();
    const { data: status, error: statusError, refetch } = useResource<ApiBackupsStatus>(user?.isSuperAdmin ? '/respaldos/resumen' : null);

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Respaldos" />

            <div className="flex flex-1 flex-col gap-4 md:gap-6">
                {user?.isSuperAdmin && (
                    <>
                        <BackupDownloadCard
                            title="Base de datos completa"
                            description="Todas las empresas. No incluye contraseñas."
                            buttonLabel="Descargar base completa"
                            icon={<DatabaseBackup className="size-5" />}
                            endpoint="/respaldos/base"
                            fallbackFilename="base-completa.sql.gz"
                        />

                        <BackupActionCard onCompleted={refetch} />

                        <BackupsStatusCard status={status} error={statusError} />
                    </>
                )}

                {user?.role === 'admin' && (
                    <BackupDownloadCard
                        title="Datos de mi empresa"
                        description="Exporta toda tu información."
                        buttonLabel="Descargar mis datos"
                        icon={<Building2 className="size-5" />}
                        endpoint="/respaldos/empresa"
                        fallbackFilename="datos-empresa.sql.gz"
                    />
                )}

                {!user?.isSuperAdmin && user?.role !== 'admin' && (
                    <Alert variant="danger" title="Sin acceso" message="Solo el administrador de la empresa puede descargar respaldos." />
                )}
            </div>
        </div>
    );
};
