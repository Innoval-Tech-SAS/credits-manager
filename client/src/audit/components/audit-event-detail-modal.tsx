import type { FC, ReactNode } from 'react';

import { Modal } from '@ui/modal/components/modal';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';
import { AUDIT_MODULE_LABELS } from '../constants/audit-modules';
import type { AuditEventDetailModalProps } from '../props/audit-event-detail-modal-props';
import { AuditActionBadge } from './audit-action-badge';

const Field: FC<{ label: string; children: ReactNode }> = ({ label, children }) => (
    <div>
        <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">{label}</dt>
        <dd className="mt-0.5 text-sm break-words text-gray-800 dark:text-white/90">{children}</dd>
    </div>
);

const JsonBlock: FC<{ title: string; value: Record<string, unknown> | null }> = ({ title, value }) => (
    <div>
        <h4 className="mb-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">{title}</h4>
        {value === null ? (
            <p className="text-sm text-gray-400 dark:text-gray-500">—</p>
        ) : (
            <pre className="custom-scrollbar max-h-64 overflow-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {JSON.stringify(value, null, 2)}
            </pre>
        )}
    </div>
);

export const AuditEventDetailModal: FC<AuditEventDetailModalProps> = ({ event, companies, onClose }) => {
    const companyName =
        event?.empresa_id !== null && event?.empresa_id !== undefined
            ? (companies?.find((company) => company.id === event.empresa_id)?.nombre_empresa ?? `Empresa #${event.empresa_id}`)
            : 'Sin empresa (plataforma)';

    return (
        <Modal isOpen={event !== null} onClose={onClose} size="lg">
            {event && (
                <div>
                    <h3 className="mb-1 pr-12 text-lg font-semibold text-gray-800 dark:text-white/90">Detalle del evento</h3>
                    <p className="mb-5 text-sm text-gray-500 dark:text-gray-400">{event.descripcion}</p>

                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Fecha y hora">{formatDateTimeEs(event.fecha_hora)}</Field>
                        <Field label="Acción">
                            <AuditActionBadge action={event.accion} />
                        </Field>
                        <Field label="Autor">
                            {event.autor_nombre ?? '—'}
                            {event.autor_usuario && <span className="text-gray-500 dark:text-gray-400"> · @{event.autor_usuario}</span>}
                        </Field>
                        <Field label="Rol">{event.es_superadmin ? 'Superadmin' : (event.autor_rol ?? '—')}</Field>
                        <Field label="Módulo">{AUDIT_MODULE_LABELS[event.modulo] ?? event.modulo}</Field>
                        <Field label="Entidad">
                            {event.entidad ? `${event.entidad}${event.entidad_id !== null ? ` #${event.entidad_id}` : ''}` : '—'}
                        </Field>
                        <Field label="Empresa">{companyName}</Field>
                        <Field label="Dirección IP">{event.ip ?? '—'}</Field>
                        {event.motivo && <Field label="Motivo">{event.motivo}</Field>}
                        <Field label="Id de evento">
                            <span className="font-mono text-xs">{event.evento_id}</span>
                        </Field>
                    </dl>

                    {(event.valores_anteriores !== null || event.valores_nuevos !== null) && (
                        <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <JsonBlock title="Valores anteriores" value={event.valores_anteriores} />
                            <JsonBlock title="Valores nuevos" value={event.valores_nuevos} />
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
};
