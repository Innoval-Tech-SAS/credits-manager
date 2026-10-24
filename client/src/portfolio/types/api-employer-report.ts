import type { ApiEmployer } from '../../employers/types/api-employer';
import type { InstallmentStatus } from '../../credits/types/installment-status';

/** Raw shape of a row nested in `ApiEmployerReport.filas` (`GET /cartera/reporte-empleador`). */
export type ApiEmployerReportRow = {
    cliente_id: number;
    cliente_nombre: string;
    valor_cuota: string;
    cuotas: Record<string, { valor: string; estado: InstallmentStatus }>;
};

/** Raw shape returned by `GET /cartera/reporte-empleador`. */
export type ApiEmployerReport = {
    empleadores: ApiEmployer[];
    empleador_seleccionado: number | null;
    meses: string[];
    filas: ApiEmployerReportRow[];
    totales_por_mes: Record<string, number>;
};
