export type PageHeaderContextValue = {
    title: string;
    setTitle: (title: string) => void;
    /** Cuando es `true`, `AppPageHeader` sigue mostrando la fila (Volver + breadcrumb) pero
     * omite el `<h2>` del título — lo usan páginas como `ModuleListPage` que ya muestran el
     * título ellas mismas, junto al contador, en vez de duplicarlo arriba. */
    hideTitle: boolean;
    setHideTitle: (hideTitle: boolean) => void;
};
