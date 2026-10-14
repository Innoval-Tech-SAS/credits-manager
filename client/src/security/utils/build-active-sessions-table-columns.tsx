import type { DataTableColumn } from '@ui/table/props/data-table-props';
import { LogOut, Monitor, Smartphone } from 'lucide-react';
import { ActionButton } from '@ui/action-button/components/action-button';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import type { ApiActiveSession } from '../types/api-active-session';
import { isMobileUserAgent } from './is-mobile-user-agent';

export function buildActiveSessionsTableColumns(
    onViewDetail: (session: ApiActiveSession) => void,
    onClose: (session: ApiActiveSession) => void,
): DataTableColumn<ApiActiveSession>[] {
    return [
        {
            key: 'dispositivo',
            label: 'Dispositivo',
            render: (session) => (
                <ActionButton
                    icon={isMobileUserAgent(session.user_agent) ? <Smartphone className="size-5" /> : <Monitor className="size-5" />}
                    label="Ver detalle del dispositivo"
                    onClick={() => onViewDetail(session)}
                />
            ),
        },
        {
            key: 'usuario',
            label: 'Usuario',
            render: (session) => (
                <div className="min-w-40">
                    <p className="text-sm font-medium text-gray-800 dark:text-white/90">{session.usuario_nombre}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400">{session.usuario_login}</p>
                </div>
            ),
        },
        {
            key: 'empresa',
            label: 'Empresa',
            render: (session) => (session.es_superadmin ? 'Super Admin' : (session.nombre_empresa ?? '—')),
        },
        { key: 'ip_address', label: 'IP', render: (session) => session.ip_address ?? '—' },
        {
            key: 'user_agent',
            label: 'Navegador',
            render: (session) => (
                <span className="block max-w-72 truncate text-xs text-gray-500 lg:max-w-md dark:text-gray-400" title={session.user_agent ?? undefined}>
                    {session.user_agent ?? '—'}
                </span>
            ),
        },
        {
            key: 'last_activity',
            label: 'Última actividad',
            render: (session) => (
                <span className="whitespace-nowrap">{formatDateTimeEs(new Date(session.last_activity * 1000).toISOString())}</span>
            ),
        },
        {
            key: 'acciones',
            label: 'Acción',
            render: (session) => <ActionButton icon={<LogOut className="size-5" />} label="Cerrar sesión" onClick={() => onClose(session)} />,
        },
    ];
}
