import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { Button } from '@ui/button/components/button';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { LifeBuoy } from 'lucide-react';
import { useAuth } from '../../auth/hooks/use-auth';
import { canAccessAudit } from '../../auth/utils/can-access-audit';
import { ExportButton } from '../../shared/components/export-button';
import { useDebouncedValue } from '../../shared/hooks/use-debounced-value';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { EMPTY_AUDIT_FILTERS } from '../constants/empty-audit-filters';
import type { AuditLogViewProps } from '../props/audit-log-view-props';
import type { ApiAuditEvent } from '../types/api-audit-event';
import type { AuditFilters as AuditFiltersState } from '../types/audit-filters';
import { buildAuditTableColumns } from '../utils/build-audit-table-columns';
import { AuditEventDetailModal } from './audit-event-detail-modal';
import { AuditFilters } from './audit-filters';

export const AuditLogView: FC<AuditLogViewProps> = ({ companies, users }) => {
    const navigate = useNavigate();
    const { user } = useAuth();
    const canExport = canAccessAudit(user, 'exportar_bitacora');

    const [draftFilters, setDraftFilters] = useState<AuditFiltersState>(EMPTY_AUDIT_FILTERS);
    const [resetToken, setResetToken] = useState(0);
    const [selectedEvent, setSelectedEvent] = useState<ApiAuditEvent | null>(null);
    const [exportMessage, setExportMessage] = useState<string | null>(null);
    const [exportError, setExportError] = useState<string | null>(null);

    // Los filtros de texto se aplican con retardo; lo que se exporta es exactamente lo que se ve en pantalla.
    const filters = useDebouncedValue(draftFilters);

    const { items, isLoading, error, page, setPage, totalPages, totalItems } = usePaginatedResource<ApiAuditEvent>('/auditoria/bitacora', filters);

    const handleFilterChange = (partial: Partial<AuditFiltersState>) => {
        setDraftFilters((current) => ({ ...current, ...partial }));
        setPage(1);
    };

    const handleReset = () => {
        setDraftFilters(EMPTY_AUDIT_FILTERS);
        setResetToken((token) => token + 1);
        setPage(1);
    };

    return (
        <ModuleListPage
            pageTitle="Bitácora de auditoría"
            successMessage={exportMessage}
            onDismissSuccess={() => setExportMessage(null)}
            errorMessage={exportError ?? error}
            onDismissError={() => setExportError(null)}
            countLabel={`${totalItems} ${totalItems === 1 ? 'evento registrado' : 'eventos registrados'}`}
            toolbar={
                <>
                    {user?.isSuperAdmin && (
                        <Button iconOnly variant="outline" startIcon={<LifeBuoy className="size-4" />} onClick={() => navigate('/recuperacion-auditoria')}>
                            Recuperación
                        </Button>
                    )}

                    {canExport && (
                        <ExportButton
                            endpoint="/auditoria/bitacora/exportar"
                            filters={filters}
                            disabled={totalItems === 0}
                            onRequested={() => {
                                setExportError(null);
                                setExportMessage('Exportación solicitada. Se genera en segundo plano y te avisaremos aquí cuando esté lista para descargar.');
                            }}
                            onError={(message) => {
                                setExportMessage(null);
                                setExportError(message);
                            }}
                        />
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={<AuditEventDetailModal event={selectedEvent} companies={companies} onClose={() => setSelectedEvent(null)} />}
        >
            <AuditFilters filters={draftFilters} companies={companies} users={users} onChange={handleFilterChange} onReset={handleReset} resetToken={resetToken} />

            <DataTable
                columns={buildAuditTableColumns(companies, setSelectedEvent)}
                rows={items}
                getRowKey={(row) => row.id}
                loading={isLoading}
                maxHeightClassName="max-h-[65vh]"
                emptyMessage="No hay eventos para los filtros seleccionados."
            />
        </ModuleListPage>
    );
};
