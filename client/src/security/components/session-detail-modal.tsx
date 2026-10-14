import type { FC } from 'react';
import { Monitor, Smartphone } from 'lucide-react';

import { Modal } from '@ui/modal/components/modal';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import type { SessionDetailModalProps } from '../props/session-detail-modal-props';
import { isMobileUserAgent } from '../utils/is-mobile-user-agent';
import { parseBrowserName } from '../utils/parse-browser-name';

export const SessionDetailModal: FC<SessionDetailModalProps> = ({ session, onClose }) => (
    <Modal isOpen={session !== null} onClose={onClose} size="sm">
        {session && (
            <div className="flex flex-col gap-5 pr-10">
                <div className="flex items-start gap-3">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                        {isMobileUserAgent(session.user_agent) ? <Smartphone className="size-6" /> : <Monitor className="size-6" />}
                    </span>

                    <div className="flex flex-col gap-1">
                        <h3 className="text-lg font-semibold text-gray-800 dark:text-white/90">{session.usuario_nombre}</h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">{session.usuario_login}</p>
                    </div>
                </div>

                <dl className="flex flex-col gap-4">
                    <div>
                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Empresa</dt>
                        <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">
                            {session.es_superadmin ? 'Super Admin' : (session.nombre_empresa ?? '—')}
                        </dd>
                    </div>

                    <div>
                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Dirección IP</dt>
                        <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">{session.ip_address ?? '—'}</dd>
                    </div>

                    <div>
                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Dispositivo</dt>
                        <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">{isMobileUserAgent(session.user_agent) ? 'Móvil' : 'Escritorio'}</dd>
                    </div>

                    <div>
                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Navegador</dt>
                        <dd className="mt-1 text-sm font-medium text-gray-800 dark:text-white/90">{parseBrowserName(session.user_agent)}</dd>
                        <dd className="mt-1 text-xs wrap-break-word text-gray-500 dark:text-gray-400">{session.user_agent ?? '—'}</dd>
                    </div>

                    <div>
                        <dt className="text-theme-xs font-medium text-gray-500 dark:text-gray-400">Última actividad</dt>
                        <dd className="mt-1 text-sm text-gray-800 dark:text-white/90">
                            {formatDateTimeEs(new Date(session.last_activity * 1000).toISOString())}
                        </dd>
                    </div>
                </dl>
            </div>
        )}
    </Modal>
);
