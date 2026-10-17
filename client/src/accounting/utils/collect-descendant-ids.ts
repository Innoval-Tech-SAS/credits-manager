import type { ApiAccount } from '../types/api-account';

/** Ids de la cuenta y de todas sus subcuentas: ninguna puede ser su cuenta padre sin formar un ciclo. */
export function collectDescendantIds(cuentas: ApiAccount[], cuentaId: number): Set<number> {
    const hijasPorPadre = new Map<number, number[]>();
    for (const cuenta of cuentas) {
        if (cuenta.cuenta_padre_id !== null) {
            hijasPorPadre.set(cuenta.cuenta_padre_id, [...(hijasPorPadre.get(cuenta.cuenta_padre_id) ?? []), cuenta.id]);
        }
    }

    const excluidas = new Set<number>([cuentaId]);
    const pendientes = [cuentaId];
    while (pendientes.length > 0) {
        for (const hija of hijasPorPadre.get(pendientes.pop() as number) ?? []) {
            if (!excluidas.has(hija)) {
                excluidas.add(hija);
                pendientes.push(hija);
            }
        }
    }

    return excluidas;
}
