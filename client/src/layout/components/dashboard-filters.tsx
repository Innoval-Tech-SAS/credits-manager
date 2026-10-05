import type { FC } from 'react';
import { useMemo } from 'react';

import { ClientSelector } from '../../clients/components/client-selector';
import type { ApiAdvisor } from '../../clients/types/api-advisor';
import { useResource } from '../../shared/hooks/use-resource';
import { opcionesDePeriodo } from '../../goals/utils/periodo-meta';
import { CREDIT_STATUS_LABEL } from '../../credits/constants/credit-status-label';
import type { ApiCredit } from '../../credits/types/api-credit';
import { Button } from '@ui/button/components/button';
import { Label } from '@ui/forms/components/label';
import type { DashboardFiltersProps } from '../props/dashboard-filters-props';
import { DashboardFilterSelect } from './dashboard-filter-select';

const ESTADOS_OPCIONES = Object.entries(CREDIT_STATUS_LABEL).map(([value, label]) => ({ value, label }));

function esEstadoCredito(valor: string): valor is ApiCredit['estado'] {
    return valor in CREDIT_STATUS_LABEL;
}

export const DashboardFilters: FC<DashboardFiltersProps> = ({ filtros, onChange, empresas, mostrarAsesor, mostrarCliente }) => {
    const periodos = useMemo(() => opcionesDePeriodo(24), []);
    const { data: asesores } = useResource<{ asesores: ApiAdvisor[] }>(mostrarAsesor ? '/clientes/asesores-asignables' : null);

    return (
        <section className="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4">
                <div className="min-w-0">
                    <Label>Período</Label>
                    <DashboardFilterSelect
                        options={periodos}
                        value={filtros.periodo}
                        onChange={(periodo) => onChange({ periodo })}
                        placeholder="Selecciona un período"
                    />
                </div>

                {empresas !== null && (
                    <div className="min-w-0">
                        <Label>Empresa</Label>
                        <DashboardFilterSelect
                            options={empresas.map((empresa) => ({ value: String(empresa.id), label: empresa.nombre_empresa }))}
                            value={filtros.empresaId !== null ? String(filtros.empresaId) : ''}
                            onChange={(valor) => onChange({ empresaId: valor === '' ? null : Number(valor) })}
                            placeholder="Selecciona una empresa"
                        />
                    </div>
                )}

                {mostrarAsesor && (
                    <div className="min-w-0">
                        <Label>Asesor</Label>
                        <DashboardFilterSelect
                            options={[
                                { value: '', label: 'Todos los asesores' },
                                ...(asesores?.asesores ?? []).map((asesor) => ({ value: String(asesor.id), label: asesor.nombre })),
                            ]}
                            value={filtros.asesorId !== null ? String(filtros.asesorId) : ''}
                            onChange={(valor) => onChange({ asesorId: valor === '' ? null : Number(valor) })}
                            placeholder="Todos los asesores"
                        />
                    </div>
                )}

                {mostrarCliente && (
                    <div className="min-w-0">
                        <Label>Cliente</Label>
                        <div className="flex items-center gap-2">
                            <div className="min-w-0 flex-1">
                                <ClientSelector selected={filtros.cliente} onSelect={(cliente) => onChange({ cliente })} />
                            </div>
                            {filtros.cliente !== null && (
                                <Button size="sm" variant="outline" onClick={() => onChange({ cliente: null })}>
                                    Quitar
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <div className="min-w-0">
                    <Label>Estado del crédito</Label>
                    <DashboardFilterSelect
                        options={[{ value: '', label: 'Todos los estados' }, ...ESTADOS_OPCIONES]}
                        value={filtros.estado ?? ''}
                        onChange={(valor) => onChange({ estado: valor !== '' && esEstadoCredito(valor) ? valor : null })}
                        placeholder="Todos los estados"
                    />
                </div>
            </div>
        </section>
    );
};
