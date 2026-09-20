/** Raw shape of a permission catalog entry, as returned by `GET /permisos`. */
export type ApiPermisoCatalogItem = {
    id: number;
    modulo: string;
    accion: string;
};

/** A catalog permission with the pivot data from a user's `permisos_extra` exception. */
export type ApiPermisoExcepcion = ApiPermisoCatalogItem & {
    pivot: {
        concedido: boolean;
    };
};
