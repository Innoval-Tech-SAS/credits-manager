import type { PersonType } from '../types/person-type';
import type { ThirdPartyRole } from '../types/third-party-role';

export type ThirdPartyFormHeaderProps = {
    tipoPersona: PersonType;
    isEditMode: boolean;
    role: ThirdPartyRole;
    onRoleChange: (role: ThirdPartyRole) => void;
    activo: boolean;
    onActivoChange: (activo: boolean) => void;
};
