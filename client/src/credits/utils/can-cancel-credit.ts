import type { ApiCredit } from '../types/api-credit';

/** Misma regla que `CreditoService::cancelar()`: Solicitado/Aprobado siempre se pueden
 * cancelar; Desembolsado solo dentro de las 24 horas siguientes al desembolso; y en
 * ningún caso si el crédito ya tiene al menos un pago registrado. */
export function puedeCancelarCredito(credito: Pick<ApiCredit, 'estado' | 'desembolsado_en'>, tienePagos: boolean): boolean {
    if (tienePagos) {
        return false;
    }

    if (credito.estado === 'solicitado' || credito.estado === 'aprobado') {
        return true;
    }

    if (credito.estado === 'desembolsado' && credito.desembolsado_en) {
        const horasTranscurridas = (Date.now() - new Date(credito.desembolsado_en).getTime()) / (1000 * 60 * 60);
        return horasTranscurridas < 24;
    }

    return false;
}
