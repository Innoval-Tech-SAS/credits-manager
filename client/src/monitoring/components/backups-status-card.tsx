import type { FC } from 'react';
import { useState } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { DataTable } from '@ui/table/components/data-table';
import { Spinner } from '@ui/spinner/components/spinner';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { BACKUP_CLASS_LABELS } from '../constants/backup-class-labels';
import type { BackupsStatusCardProps } from '../props/backups-status-card-props';
import type { BackupClass } from '../types/api-backups-status';
import { formatBytes } from '../utils/format-bytes';

const OK = 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400';
const ERROR = 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400';
const NEUTRAL = 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300';

export const BackupsStatusCard: FC<BackupsStatusCardProps> = ({ status, error }) => {
    const [clase, setClase] = useState<BackupClass>('diario');

    if (error) {
        return <Alert variant="danger" title="Respaldos" message={error} />;
    }

    if (!status) {
        return (
            <section className="flex justify-center rounded-2xl border border-gray-200 bg-white p-10 dark:border-gray-800 dark:bg-white/3">
                <Spinner />
            </section>
        );
    }

    const respaldos = status.clases.find((item) => item.clase === clase)?.respaldos ?? [];
    const externo = status.externo;

    return (
        <section className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 sm:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Respaldos</h2>
                <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${status.alerta ? ERROR : OK}`}>
                    {status.alerta ? 'Revisar' : 'Al día'}
                </span>
            </div>

            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div className="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/3">
                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">Último respaldo diario (automático a las {status.hora_automatica})</p>
                    <p className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">
                        {status.ultimo_diario
                            ? `${formatDateTimeEs(status.ultimo_diario.creado_en)} · hace ${status.horas_desde_ultimo_diario} h`
                            : 'No hay respaldos diarios'}
                    </p>
                </div>

                <div className="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/3">
                    <p className="text-theme-xs text-gray-500 dark:text-gray-400">Copia externa (S3)</p>
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                        {!externo.configurado && <span className={`rounded-full px-2.5 py-0.5 text-xs ${NEUTRAL}`}>Sin configurar</span>}
                        {externo.configurado && !externo.ultimo_resultado && 'Aún no se ha subido ninguno'}
                        {externo.configurado && externo.ultimo_resultado && (
                            <>
                                <span className={`rounded-full px-2.5 py-0.5 text-xs ${externo.ultimo_resultado.exitoso ? OK : ERROR}`}>
                                    {externo.ultimo_resultado.exitoso ? 'Subido' : 'Falló'}
                                </span>
                                <span className="font-normal text-gray-500 dark:text-gray-400">
                                    {externo.ultimo_resultado.detalle ?? formatDateTimeEs(externo.ultimo_resultado.registrado_en)}
                                </span>
                            </>
                        )}
                    </p>
                </div>
            </div>

            <div className="mb-3 flex flex-wrap gap-2">
                {status.clases.map((item) => (
                    <button
                        key={item.clase}
                        type="button"
                        onClick={() => setClase(item.clase)}
                        className={`rounded-lg px-3 py-1.5 text-theme-sm font-medium ${
                            clase === item.clase
                                ? 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400'
                                : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90'
                        }`}
                    >
                        {BACKUP_CLASS_LABELS[item.clase]} ({item.respaldos.length})
                    </button>
                ))}
            </div>

            <DataTable
                columns={[
                    { key: 'creado_en', label: 'Fecha', render: (row) => <span className="whitespace-nowrap">{formatDateTimeEs(row.creado_en)}</span> },
                    { key: 'tamano_bytes', label: 'Tamaño', render: (row) => formatBytes(row.tamano_bytes) },
                    {
                        key: 'completo',
                        label: 'Estado',
                        render: (row) => (
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${row.completo ? OK : ERROR}`}>
                                {row.completo ? 'Completo' : 'Incompleto'}
                            </span>
                        ),
                    },
                ]}
                rows={respaldos}
                getRowKey={(row) => row.carpeta}
                emptyMessage="No hay respaldos de esta clase."
                maxHeightClassName="max-h-80"
            />
        </section>
    );
};
