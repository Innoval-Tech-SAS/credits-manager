import type { BreadcrumbItem } from './breadcrumb-item';

export type PageHeaderContextValue = {
    title: string;
    setTitle: (title: string) => void;
    /** `null` = sin override: la ruta de migas sale del sidebar (`findNavBreadcrumb`). */
    trail: BreadcrumbItem[] | null;
    setTrail: (trail: BreadcrumbItem[] | null) => void;
};
