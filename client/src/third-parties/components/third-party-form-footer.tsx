import type { FC } from 'react';
import { Save } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ThirdPartyFormFooterProps } from '../props/third-party-form-footer-props';

export const ThirdPartyFormFooter: FC<ThirdPartyFormFooterProps> = ({ isEditMode, isSubmitting }) => {
    return (
        <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
            <Button
                type="submit"
                disabled={isSubmitting}
                startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                endIcon={isSubmitting ? undefined : <Save className="size-4" />}
            >
                {isSubmitting
                    ? isEditMode
                        ? 'Actualizando…'
                        : 'Creando…'
                    : isEditMode
                      ? 'Actualizar tercero'
                      : 'Crear tercero'}
            </Button>
        </div>
    );
};
