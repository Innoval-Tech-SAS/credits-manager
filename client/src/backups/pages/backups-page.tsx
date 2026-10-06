import type { FC } from 'react';

import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Alert } from '@ui/alert/components/alert';
import { DatabaseBackup, Building2 } from 'lucide-react';
import { useAuth } from '../../auth/hooks/use-auth';
import { BackupDownloadCard } from '../components/backup-download-card';

/** Superadmin: base completa de todas las empresas. Administrador de empresa: solo los datos de la suya. */
export const BackupsPage: FC = () => {
    const { user } = useAuth();

    return (
        <>
            <PageBreadcrumb pageTitle="Respaldos" />

            <div className="flex flex-col gap-4 md:gap-6">
                {user?.isSuperAdmin && (
                    <BackupDownloadCard
                        title="Base de datos completa"
                        description="Todas las empresas, con su estructura. Las contraseñas y los tokens de sesión no se incluyen."
                        buttonLabel="Descargar base completa"
                        icon={<DatabaseBackup className="size-5" />}
                        endpoint="/respaldos/base"
                        fallbackFilename="base-completa.sql.gz"
                    />
                )}

                {user?.role === 'admin' && (
                    <BackupDownloadCard
                        title="Datos de mi empresa"
                        description="Todas las tablas, solo con los registros de tu empresa. Las contraseñas no se incluyen."
                        buttonLabel="Descargar datos de mi empresa"
                        icon={<Building2 className="size-5" />}
                        endpoint="/respaldos/empresa"
                        fallbackFilename="datos-empresa.sql.gz"
                    />
                )}

                {!user?.isSuperAdmin && user?.role !== 'admin' && (
                    <Alert variant="danger" title="Sin acceso" message="Solo el administrador de la empresa o el superadmin pueden descargar respaldos." />
                )}
            </div>
        </>
    );
};
