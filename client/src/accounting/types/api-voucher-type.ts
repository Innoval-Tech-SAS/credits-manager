/** Raw shape of a row inside `GET /contabilidad/tipos-comprobante`'s paginated `data`, and of `POST`/`GET {id}`/`PATCH`. */
export type ApiVoucherType = {
    id: number;
    codigo: string;
    nombre: string;
    activo: boolean;
    consecutivo_actual: number;
    consecutivo_inicial: number;
    consecutivo_final: number | null;
    tipo_numeracion: 'automatico' | 'manual';
    reinicio_periodo: 'nunca' | 'anual' | 'mensual';
    proximo_a_agotarse: boolean;
    /** Solo viene en `GET {id}`: deshabilita `consecutivo_inicial` en el formulario antes de chocar con un 422. */
    tiene_comprobantes_asociados?: boolean;
};
