import type { FC } from 'react';
import { Activity, Building2, CalendarClock, DatabaseBackup, KeyRound, Users } from 'lucide-react';

import type { SuperadminHomeCardsProps } from '../props/superadmin-home-cards-props';
import { StatCard } from './stat-card';

/** 6 tarjetas de plataforma, ninguna de datos de negocio, cada una lleva a su pantalla. */
export const SuperadminHomeCards: FC<SuperadminHomeCardsProps> = ({ panel }) => (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
        <StatCard label="Logins fallidos (7 días)" value={String(panel.logins_fallidos_7_dias)} icon={KeyRound} to="/auditoria" />

        {/* Sin `to`: su pantalla (Seguridad) todavía no existe. Enlazar a /seguridad cuando esa fase se implemente. */}
        <StatCard label="Usuarios activos" value={String(panel.usuarios_activos)} icon={Users} />

        <StatCard label="Empresas activas" value={String(panel.empresas_activas)} icon={Building2} to="/empresas" />

        <StatCard
            label="Servicios caídos"
            value={String(panel.servicios_caidos)}
            icon={Activity}
            accent={panel.servicios_caidos > 0 ? 'error' : 'success'}
            to="/monitoreo"
        />

        <StatCard
            label="Último respaldo"
            value={panel.ultimo_respaldo.horas_desde_ultimo_diario === null ? 'Sin respaldos' : `hace ${panel.ultimo_respaldo.horas_desde_ultimo_diario} h`}
            icon={DatabaseBackup}
            accent={panel.ultimo_respaldo.alerta ? 'error' : 'success'}
            to="/respaldos"
        />

        <StatCard
            label="Errores de tareas (mes)"
            value={String(panel.errores_tareas_mes)}
            icon={CalendarClock}
            accent={panel.errores_tareas_mes > 0 ? 'error' : 'neutral'}
            to="/tareas-programadas"
        />
    </div>
);
