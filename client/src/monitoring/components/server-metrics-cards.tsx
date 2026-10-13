import type { FC } from 'react';
import { Cpu, Gauge, HardDrive, MemoryStick } from 'lucide-react';

import { StatCard } from '../../layout/components/stat-card';
import type { ServerMetricsCardsProps } from '../props/server-metrics-cards-props';
import { formatBytes } from '../utils/format-bytes';
import { formatPercentage } from '../utils/format-percentage';
import { formatUptime } from '../utils/format-uptime';
import { usageAccent } from '../utils/usage-accent';

export const ServerMetricsCards: FC<ServerMetricsCardsProps> = ({ servidor }) => {
    const { cpu, memoria, disco, carga, encendido_segundos } = servidor;

    return (
        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 xl:grid-cols-4">
            <StatCard
                label={cpu ? `CPU · ${cpu.nucleos} ${cpu.nucleos === 1 ? 'núcleo' : 'núcleos'}` : 'CPU'}
                value={formatPercentage(cpu?.porcentaje)}
                icon={Cpu}
                accent={usageAccent(cpu?.porcentaje)}
            />
            <StatCard
                label={memoria ? `Memoria · ${formatBytes(memoria.usada_bytes)} de ${formatBytes(memoria.total_bytes)}` : 'Memoria'}
                value={formatPercentage(memoria?.porcentaje)}
                icon={MemoryStick}
                accent={usageAccent(memoria?.porcentaje)}
            />
            <StatCard
                label={disco ? `Disco · ${formatBytes(disco.usado_bytes)} de ${formatBytes(disco.total_bytes)}` : 'Disco'}
                value={formatPercentage(disco?.porcentaje)}
                icon={HardDrive}
                accent={usageAccent(disco?.porcentaje)}
            />
            <StatCard
                label={`Carga 1 / 5 / 15 min · encendido ${formatUptime(encendido_segundos)}`}
                value={carga ? carga.map((valor) => valor.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })).join(' / ') : '—'}
                icon={Gauge}
                accent={carga && cpu && carga[0] > cpu.nucleos ? 'error' : 'neutral'}
            />
        </div>
    );
};
