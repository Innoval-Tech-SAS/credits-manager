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
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Respaldos" />

            <div className="flex flex-1 flex-col gap-4 md:gap-6">
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
                        description="Exporta toda tu información."
                        buttonLabel="Descargar mis datos"
                        icon={<Building2 className="size-5" />}
                        endpoint="/respaldos/empresa"
                        fallbackFilename="datos-empresa.sql.gz"
                    />
                )}

                {!user?.isSuperAdmin && user?.role !== 'admin' && (
                    <Alert variant="danger" title="Sin acceso" message="Solo el administrador de la empresa o el superadmin pueden descargar respaldos." />
                )}
            </div>
        </div>
    );
};
