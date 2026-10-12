import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Search, X } from 'lucide-react';
import { AUDIT_ACTION_OPTIONS } from '../constants/audit-actions';
import { AUDIT_MODULE_OPTIONS } from '../constants/audit-modules';
import type { AuditFiltersProps } from '../props/audit-filters-props';

export const AuditFilters: FC<AuditFiltersProps> = ({ filters, companies, onChange, onReset, resetToken }) => (
    <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div>
            <Label htmlFor="audit-desde">Desde</Label>
            <DatePicker
                key={`desde-${resetToken}`}
                id="audit-desde"
                value={filters.desde}
                onChange={(value) => onChange({ desde: value })}
                placeholder="Fecha inicial"
            />
        </div>

        <div>
            <Label htmlFor="audit-hasta">Hasta</Label>
            <DatePicker
                key={`hasta-${resetToken}`}
                id="audit-hasta"
                value={filters.hasta}
                onChange={(value) => onChange({ hasta: value })}
                placeholder="Fecha final"
            />
        </div>

        <div>
            <Label htmlFor="audit-modulo">Módulo</Label>
            <Select
                id="audit-modulo"
                key={`modulo-${resetToken}`}
                options={AUDIT_MODULE_OPTIONS}
                placeholder="Todos los módulos"
                placeholderSelectable
                defaultValue={filters.modulo}
                onChange={(value) => onChange({ modulo: value })}
            />
        </div>

        <div>
            <Label htmlFor="audit-accion">Acción</Label>
            <Select
                id="audit-accion"
                key={`accion-${resetToken}`}
                options={AUDIT_ACTION_OPTIONS}
                placeholder="Todas las acciones"
                placeholderSelectable
                defaultValue={filters.accion}
                onChange={(value) => onChange({ accion: value })}
            />
        </div>

        <div>
            <Label htmlFor="audit-autor">Autor</Label>
            <Input
                id="audit-autor"
                placeholder="Nombre o usuario"
                startIcon={<Search className="size-4" />}
                value={filters.autor}
                onChange={(event) => onChange({ autor: event.target.value })}
            />
        </div>

        <div>
            <Label htmlFor="audit-entidad">Entidad</Label>
            <Input
                id="audit-entidad"
                placeholder="Ej. credito, cliente, empresa"
                value={filters.entidad}
                onChange={(event) => onChange({ entidad: event.target.value })}
            />
        </div>

        {companies !== null && (
            <div>
                <Label htmlFor="audit-empresa">Empresa</Label>
                <Select
                    id="audit-empresa"
                    key={`empresa-${resetToken}`}
                    options={companies.map((company) => ({ value: String(company.id), label: company.nombre_empresa }))}
                    placeholder="Todas las empresas"
                    placeholderSelectable
                    defaultValue={filters.empresa_id}
                    onChange={(value) => onChange({ empresa_id: value })}
                />
            </div>
        )}

        <div className="flex items-end">
            <Button iconOnly variant="outline" startIcon={<X className="size-4" />} onClick={onReset}>
                Limpiar filtros
            </Button>
        </div>
    </div>
);
