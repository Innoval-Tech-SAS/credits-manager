/** Raw shape of each row returned by `GET /monitoreo/trabajos-fallidos`. */
export type ApiFailedJob = {
    uuid: string;
    cola: string;
    trabajo: string;
    error: string;
    fallido_en: string | null;
};
