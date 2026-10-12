import type { FC } from 'react';
import { useLayoutEffect } from 'react';

import { usePageHeader } from '../hooks/use-page-header';
import type { PageBreadcrumbProps } from '../props/page-breadcrumb-props';

/**
 * Registra el título de la página en el encabezado fijo del layout (junto al botón Volver y la ruta de migas)
 * en vez de dibujarlo aquí mismo: así cada página conserva su título dinámico y el encabezado no se va con el scroll.
 */
export const PageBreadcrumb: FC<PageBreadcrumbProps> = ({ pageTitle }) => {
    const { setTitle } = usePageHeader();

    useLayoutEffect(() => {
        setTitle(pageTitle);
        return () => setTitle('');
    }, [pageTitle, setTitle]);

    return null;
};
