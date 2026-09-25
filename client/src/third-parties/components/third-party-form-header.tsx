import type { FC } from 'react';

import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { ToggleSwitchInput } from '@ui/forms/components/toggle-switch-input';
import { THIRD_PARTY_ROLE_OPTIONS } from '../constants/third-party-role-options';
import type { ThirdPartyFormHeaderProps } from '../props/third-party-form-header-props';
import type { ThirdPartyRole } from '../types/third-party-role';

export const ThirdPartyFormHeader: FC<ThirdPartyFormHeaderProps> = ({ isEditMode, role, onRoleChange, activo, onActivoChange }) => {
    return isEditMode ? (
        <div>
            <Label htmlFor="activo">Estado</Label>
            <div className="flex h-11 items-center gap-2.5">
                <ToggleSwitchInput id="activo" checked={activo} onChange={onActivoChange} />
                <span className="text-sm text-gray-600 dark:text-gray-300">{activo ? 'Activo' : 'Inactivo'}</span>
            </div>
        </div>
    ) : (
        <div>
            <Label htmlFor="rol">Tipo</Label>
            <Select options={THIRD_PARTY_ROLE_OPTIONS} defaultValue={role} onChange={(value) => onRoleChange(value as ThirdPartyRole)} />
        </div>
    );
};
