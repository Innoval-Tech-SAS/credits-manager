import type { ThirdPartyRole } from '../types/third-party-role';

export type ThirdPartyFormHeaderProps = {
    isEditMode: boolean;
    role: ThirdPartyRole;
    onRoleChange: (role: ThirdPartyRole) => void;
    activo: boolean;
    onActivoChange: (activo: boolean) => void;
};
