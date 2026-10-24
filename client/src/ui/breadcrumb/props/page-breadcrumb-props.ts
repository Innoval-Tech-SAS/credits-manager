import type { BreadcrumbItem } from '../types/breadcrumb-item';

export type PageBreadcrumbProps = {
    pageTitle: string;
    /** Override de la ruta de migas cuando la página no tiene entrada en el sidebar (ej. "Notificaciones"). Por defecto sale del sidebar. */
    trail?: BreadcrumbItem[];
};
