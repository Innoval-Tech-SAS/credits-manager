import type { FC } from 'react';
import { useMemo, useState } from 'react';

import { Alert } from '@ui/alert/components/alert';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { Pagination } from '@ui/table/components/pagination';
import { AUDIT_ACTION_BADGE_CLASSES, AUDIT_ACTION_LABELS } from '../../audit/constants/audit-actions';
import type { ApiAuditEvent } from '../../audit/types/api-audit-event';
import type { ApiCompany } from '../../company/types/api-company';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useResource } from '../../shared/hooks/use-resource';
import type { PaginatedResponse } from '../../shared/types/paginated-response';
import { formatDateTimeEs } from '../../shared/utils/format-datetime-es';

/** Mismo origen que la bitácora (`BitacoraAuditoria`, sin `EmpresaScope`), acotado a login/logout/login fallido. */
export const AccessHistoryPage: FC = () => {
    const [desde, setDesde] = useState('');
    const [hasta, setHasta] = useState('');
    const [empresaId, setEmpresaId] = useState('');

    const { items, isLoading, error, page, setPage, totalPages, totalItems } = usePaginatedResource<ApiAuditEvent>('/seguridad/accesos', {
        desde,
        hasta,
        empresa_id: empresaId,
    });

    const { data: alerta } = useResource<{ alerta_fuerza_bruta: boolean }>('/seguridad/accesos/alerta');
    const { data: empresasPagina } = useResource<PaginatedResponse<ApiCompany>>('/empresas?per_page=100');

    const empresas = empresasPagina?.data ?? [];
    const nombreEmpresaPorId = useMemo(
        () => new Map((empresasPagina?.data ?? []).map((empresa) => [empresa.id, empresa.nombre_empresa])),
        [empresasPagina],
    );

    return (
        <ModuleListPage
            pageTitle="Historial de accesos"
            errorMessage={error}
            countLabel={`${totalItems} ${totalItems === 1 ? 'evento registrado' : 'eventos registrados'}`}
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
        >
            {alerta?.alerta_fuerza_bruta && (
                <div className="mb-4">
                    <Alert
                        variant="danger"
                        title="Posible fuerza bruta"
                        message="Varios intentos de inicio de sesión fallidos desde la misma IP en los últimos 10 minutos."
                    />
                </div>
            )}

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <Label htmlFor="accesos-desde">Desde</Label>
                    <DatePicker
                        id="accesos-desde"
                        value={desde}
                        onChange={(value) => {
                            setDesde(value);
                            setPage(1);
                        }}
                        placeholder="Fecha inicial"
                    />
                </div>

                <div>
                    <Label htmlFor="accesos-hasta">Hasta</Label>
                    <DatePicker
                        id="accesos-hasta"
                        value={hasta}
                        onChange={(value) => {
                            setHasta(value);
                            setPage(1);
                        }}
                        placeholder="Fecha final"
                    />
                </div>

                <div>
                    <Label htmlFor="accesos-empresa">Empresa</Label>
                    <Select
                        id="accesos-empresa"
                        options={empresas.map((empresa) => ({ value: String(empresa.id), label: empresa.nombre_empresa }))}
                        placeholder="Todas las empresas"
                        placeholderSelectable
                        defaultValue={empresaId}
                        onChange={(value) => {
                            setEmpresaId(value);
                            setPage(1);
                        }}
                    />
                </div>
            </div>

            <DataTable
                columns={[
                    {
                        key: 'fecha_hora',
                        label: 'Fecha',
                        render: (evento) => <span className="whitespace-nowrap">{formatDateTimeEs(evento.fecha_hora)}</span>,
                    },
                    {
                        key: 'accion',
                        label: 'Acción',
                        render: (evento) => (
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${AUDIT_ACTION_BADGE_CLASSES[evento.accion]}`}
                            >
                                {AUDIT_ACTION_LABELS[evento.accion]}
                            </span>
                        ),
                    },
                    { key: 'autor_nombre', label: 'Autor', render: (evento) => evento.autor_nombre ?? '—' },
                    {
                        key: 'empresa_id',
                        label: 'Empresa',
                        render: (evento) => (evento.empresa_id === null ? 'Super Admin' : (nombreEmpresaPorId.get(evento.empresa_id) ?? `#${evento.empresa_id}`)),
                    },
                ]}
                rows={items}
                getRowKey={(evento) => evento.id}
                loading={isLoading}
                emptyMessage="No hay accesos para los filtros seleccionados."
            />
        </ModuleListPage>
    );
};
