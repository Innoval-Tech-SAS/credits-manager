import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { Button } from '@ui/button/components/button';
import { Select } from '@ui/forms/components/select';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { AUDIT_BACKUP_STATUS_OPTIONS } from '../constants/audit-backup';
import type { ApiAuditBackupEntry, ApiAuditRecoveryResult } from '../types/api-audit-backup-entry';
import { buildAuditBackupTableColumns } from '../utils/build-audit-backup-table-columns';

/** El listener inserta en milisegundos; se espera un instante antes de releer para que el estado ya salga "Recuperado". */
const REFRESH_DELAY_MS = 1500;

export const AuditRecoveryPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [estado, setEstado] = useState('pendiente');
    const [recoveringId, setRecoveringId] = useState<string | null>(null);
    const [isRecoveringAll, setIsRecoveringAll] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const { items, isLoading, error, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiAuditBackupEntry>('/auditoria/respaldo', { estado });

    const recover = async (body: { evento_ids: string[] } | { todos: true }, recoveringKey: string | null) => {
        setErrorMessage(null);
        setSuccessMessage(null);
        setRecoveringId(recoveringKey);
        setIsRecoveringAll(recoveringKey === null);

        try {
            const result = await sendRequest<ApiAuditRecoveryResult>('/auditoria/respaldo/recuperar', { method: 'POST', body });

            if (result.fallidos > 0) {
                setErrorMessage(`No se pudieron recuperar ${result.fallidos} evento(s): Redis sigue sin responder.`);
            }

            if (result.recuperados > 0) {
                setSuccessMessage(`${result.recuperados} evento(s) reenviados a la cola de auditoría. El listener los insertará en instantes.`);
            }

            setTimeout(refetch, REFRESH_DELAY_MS);
        } catch (caught) {
            setErrorMessage(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setRecoveringId(null);
            setIsRecoveringAll(false);
            setIsConfirmOpen(false);
        }
    };

    return (
        <ModuleListPage
            pageTitle="Recuperación de auditoría"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={errorMessage ?? error}
            onDismissError={() => setErrorMessage(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'entrada en el log de respaldo' : 'entradas en el log de respaldo'}`}
            toolbar={
                <>
                    <Button iconOnly variant="outline" startIcon={<ArrowLeft className="size-4" />} onClick={() => navigate('/auditoria')}>
                        Bitácora
                    </Button>

                    <div className="w-full sm:w-52">
                        <Select
                            id="filtro-estado-respaldo"
                            ariaLabel="Estado"
                            options={AUDIT_BACKUP_STATUS_OPTIONS}
                            placeholder="Todos los estados"
                            placeholderSelectable
                            defaultValue={estado}
                            onChange={(value) => {
                                setEstado(value);
                                setPage(1);
                            }}
                        />
                    </div>

                    <Button collapseBelow="md" startIcon={<RotateCcw className="size-4" />} disabled={recoveringId !== null || isRecoveringAll} onClick={() => setIsConfirmOpen(true)}>
                        Recuperar pendientes
                    </Button>
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={
                <ConfirmModal
                    isOpen={isConfirmOpen}
                    onClose={() => setIsConfirmOpen(false)}
                    onConfirm={() => void recover({ todos: true }, null)}
                    variant="warning"
                    title="Recuperar todos los pendientes"
                    description="Se reenviarán a la cola de auditoría todos los eventos del log de respaldo que aún no están en la bitácora, conservando su fecha, autor y empresa originales. Es seguro repetirlo: un evento nunca se inserta dos veces."
                    confirmLabel="Recuperar"
                    isConfirming={isRecoveringAll}
                />
            }
        >
            <p className="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Aquí aparecen los eventos que no llegaron a la bitácora (Redis caído, evento inválido o error al insertar). Solo el superadmin ve este panel, porque el
                log reúne eventos de todas las empresas.
            </p>

            <DataTable
                columns={buildAuditBackupTableColumns((entry) => entry.evento_id && void recover({ evento_ids: [entry.evento_id] }, entry.evento_id), recoveringId)}
                rows={items}
                getRowKey={(row) => `${row.evento_id ?? 'sin-id'}-${row.registrado_en}`}
                loading={isLoading}
                emptyMessage="No hay entradas en el log de respaldo para el estado seleccionado."
                maxHeightClassName="max-h-[65vh]"
            />
        </ModuleListPage>
    );
};
