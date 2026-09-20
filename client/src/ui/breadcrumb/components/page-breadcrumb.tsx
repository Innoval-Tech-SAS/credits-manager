import type { FC } from 'react';
import { useLayoutEffect } from 'react';

import { usePageHeader } from '../hooks/use-page-header';
import type { PageBreadcrumbProps } from '../props/page-breadcrumb-props';

/**
 * Registra el título de la página en el layout (donde se renderiza junto al botón Volver)
 * en vez de dibujarlo aquí mismo — así cada página conserva su título dinámico sin duplicar el header.
 */
export const PageBreadcrumb: FC<PageBreadcrumbProps> = ({ pageTitle, hideTitle = false }) => {
    const { setTitle, setHideTitle } = usePageHeader();

    useLayoutEffect(() => {
        setTitle(pageTitle);
        setHideTitle(hideTitle);
        return () => {
            setTitle('');
            setHideTitle(false);
        };
    }, [pageTitle, hideTitle, setTitle, setHideTitle]);

    return null;
};
