/** Raw shape of a row returned by `GET /gastos/parametrizaciones` and `POST /gastos/parametrizaciones`. */
export type ApiParametrization = {
    id: number;
    tipo_gasto_id: number;
    fecha_desde: string;
    fecha_hasta: string | null;
    tipo_comprobante_id: number;
    cuenta_gasto_id: number;
    cuenta_iva_id: number | null;
    tarifa_iva: string | null;
    cuenta_cxp_id: number;
    aplica_retefuente: boolean;
    concepto_dian: string | null;
    base_minima_uvt: number | null;
    tarifa_retefuente_persona_natural: string | null;
    tarifa_retefuente_persona_juridica: string | null;
    cuenta_retefuente_id: number | null;
    aplica_reteica: boolean;
    cuenta_reteica_id: number | null;
    aplica_reteiva: boolean;
    tarifa_reteiva: string | null;
    cuenta_reteiva_id: number | null;
};
