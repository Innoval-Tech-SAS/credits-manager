/** Raw shape of one line of a facturación document (`lineas` in `ApiInvoice`). */
export type ApiInvoiceLine = {
    id: number;
    orden: number;
    concepto: string;
    descripcion: string;
    /** Decimal string en pesos, ej. "1500.50". */
    valor: string;
    linea_origen_id: number | null;
    /** Soporte del cálculo, solo presente en una línea de nota débito por intereses de mora. */
    tasa_mora: string | null;
    dias_mora: number | null;
    base_mora: string | null;
};

/** Raw shape of a facturación document: `POST/GET /facturacion/documentos`. */
export type ApiInvoice = {
    id: number;
    tipo: 'factura' | 'nota_credito' | 'nota_debito';
    estado: 'borrador' | 'emitido';
    consecutivo: number | null;
    numero: string | null;
    fecha: string;
    /** Solo se usa en la nota débito: la fecha desde la que el mayor valor entra al cobro. */
    fecha_vencimiento: string | null;
    cliente_id: number;
    credito_id: number;
    documento_origen_id: number | null;
    motivo: string | null;
    emitido_en: string | null;
    cliente?: { id: number; tercero: { nombre: string } };
    origen?: { id: number; numero: string | null; tipo: ApiInvoice['tipo'] } | null;
    lineas?: ApiInvoiceLine[];
};

/** Situación visible de una factura, derivada de sus notas y cobros. Las notas no tienen situación. */
export type ApiInvoiceSituation = 'emitida' | 'parcialmente_pagada' | 'pagada' | 'con_saldo_a_favor' | 'anulada';

/** Totales de un documento. Las notas y los cobros solo se cuentan sobre la factura. */
export type ApiInvoiceTotals = {
    total: string;
    total_nota_credito?: string;
    total_nota_debito?: string;
    total_cobrado?: string;
    saldo?: string;
    /** Positivo solo cuando `saldo` queda negativo: lo que la nota crédito dejó a favor del cliente. */
    saldo_a_favor?: string;
    anulada?: boolean;
    situacion?: ApiInvoiceSituation;
};

export type ApiInvoiceNoteCreditReason = 'devolucion' | 'anulacion' | 'rebaja_descuento' | 'ajuste_precio' | 'otros';

export type ApiInvoiceNoteDebitReason = 'intereses' | 'gastos_por_cobrar' | 'cambio_valor' | 'otros';

export type ApiInvoicePaymentMethod = 'efectivo' | 'transferencia' | 'consignacion' | 'cheque' | 'tarjeta';

/** Fila de cobro: `cobro` registra dinero recibido; `reversa` lo compensa y apunta al cobro original. */
export type ApiInvoicePayment = {
    id: number;
    tipo: 'cobro' | 'reversa';
    cobro_origen_id: number | null;
    fecha: string;
    /** Decimal string en pesos, ej. "400.00". */
    valor: string;
    medio: ApiInvoicePaymentMethod;
    referencia: string | null;
    observacion: string | null;
    motivo: string | null;
    registrado_por: number | null;
    registrador?: { id: number; nombre: string } | null;
    creado_en: string;
};

/** Raw shape de `GET /facturacion/documentos/{id}`: el documento con sus líneas, sus totales y sus cobros. */
export type ApiInvoiceDetail = {
    documento: ApiInvoice;
    totales: ApiInvoiceTotals;
    cobros: ApiInvoicePayment[];
};

/** Raw shape de `GET /facturacion/consecutivos`. */
export type ApiInvoiceSequence = {
    tipo: 'factura' | 'nota_credito' | 'nota_debito';
    etiqueta: string;
    prefijo: string | null;
    siguiente: number;
    numero_inicial_editable: boolean;
};
