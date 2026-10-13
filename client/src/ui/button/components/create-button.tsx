import type { FC } from 'react';

import { Button } from './button';
import type { CreateButtonProps } from '../props/create-button-props';

/** Botón estándar para crear un recurso nuevo: mismo verbo ("Crear ") y mismo patrón de tooltip/colapso en toda la app. */
export const CreateButton: FC<CreateButtonProps> = ({ label, icon, onClick, disabled }) => (
    <Button collapseBelow="md" startIcon={icon} onClick={onClick} disabled={disabled}>
        {`Crear ${label}`}
    </Button>
);
