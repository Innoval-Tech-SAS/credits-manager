import type { FC } from 'react';

import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { PERSON_TYPE_LABELS } from '../constants/person-type-choices';
import { THIRD_PARTY_ROLE_OPTIONS } from '../constants/third-party-role-options';
import type { ThirdPartyFormHeaderProps } from '../props/third-party-form-header-props';
import type { ThirdPartyRole } from '../types/third-party-role';

export const ThirdPartyFormHeader: FC<ThirdPartyFormHeaderProps> = ({ tipoPersona, isEditMode, role, onRoleChange, activo, onActivoChange }) => {
    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <span className="w-fit rounded-full bg-brand-50 px-3 py-1 text-sm font-medium text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                {PERSON_TYPE_LABELS[tipoPersona]}
            </span>

            {!isEditMode && (
                <div className="sm:w-48">
                    <Label htmlFor="rol">Tipo</Label>
                    <Select options={THIRD_PARTY_ROLE_OPTIONS} defaultValue={role} onChange={(value) => onRoleChange(value as ThirdPartyRole)} />
                </div>
            )}

            {isEditMode && (
                <div className="sm:w-36">
                    <Label htmlFor="activo">Estado</Label>
                    <div className="flex h-11 items-center gap-2.5">
                        <ToggleSwitchInput id="activo" checked={activo} onChange={onActivoChange} />
                        <span className="text-sm text-gray-600 dark:text-gray-300">{activo ? 'Activo' : 'Inactivo'}</span>
                    </div>
                </div>
            )}
        </div>
    );
};
