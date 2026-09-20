import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ApiAccount } from '../types/api-account';
import type { ApiLedger, ApiLedgerMovement } from '../types/api-ledger';

function primerDiaDelAnio(): string {
    return `${new Date().getFullYear()}-01-01`;
}

export const LedgerPage: FC = () => {
    const { data: cuentasData } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);

    const [cuentaId, setCuentaId] = useState('');
    const [desde, setDesde] = useState(primerDiaDelAnio());
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));

    const { data, isLoading } = useResource<ApiLedger>(
        cuentaId ? `/contabilidad/libro-mayor?cuenta_id=${cuentaId}&desde=${desde}&hasta=${hasta}` : '',
    );

    // Igual que el legado: el saldo corrido acumula débito - crédito sin
    // ajustar por naturaleza (simplificación visual, no afecta el saldo inicial).
    const movimientosConSaldo = (data?.movimientos ?? []).reduce<Array<ApiLedgerMovement & { saldo: number }>>((filas, movimiento) => {
        const saldoAnterior = filas.length > 0 ? filas[filas.length - 1].saldo : Number(data?.saldo_inicial ?? 0);
        const saldo = saldoAnterior + parseFloat(movimiento.debito) - parseFloat(movimiento.credito);

        return [...filas, { ...movimiento, saldo }];
    }, []);

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Libro mayor / auxiliares" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div>
                        <Label htmlFor="cuenta">Cuenta</Label>
                        <Select
                            options={cuentas.map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }))}
                            placeholder="Seleccione..."
                            defaultValue={cuentaId}
                            onChange={setCuentaId}
                        />
                    </div>
                    <div>
                        <Label htmlFor="desde">Desde</Label>
                        <DatePicker id="desde" value={desde} onChange={setDesde} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Hasta</Label>
                        <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                    </div>
                </div>
            </div>

            {cuentaId && (
                <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    {isLoading && (
                        <div className="flex justify-center py-6">
                            <Spinner size="lg" />
                        </div>
                    )}

                    {data && (
                        <div className="custom-scrollbar overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-gray-800">
                                        {['Fecha', 'Comp.', 'Tercero', 'Descripción', 'Débito', 'Crédito', 'Saldo'].map((label) => (
                                            <th key={label} className="px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="border-b border-gray-100 dark:border-gray-800">
                                        <td colSpan={6} className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
                                            Saldo inicial
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-gray-800 dark:text-white/90">
                                            {formatMoney(data.saldo_inicial)}
                                        </td>
                                    </tr>

                                    {movimientosConSaldo.map((movimiento, index) => (
                                        <tr key={index} className="border-b border-gray-100 dark:border-gray-800">
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{formatDateEs(movimiento.fecha)}</td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {movimiento.tipo}-{movimiento.consecutivo}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{movimiento.tercero ?? '-'}</td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{movimiento.descripcion ?? ''}</td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {parseFloat(movimiento.debito) > 0 ? formatMoney(movimiento.debito) : ''}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                {parseFloat(movimiento.credito) > 0 ? formatMoney(movimiento.credito) : ''}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700 dark:text-gray-300">{formatMoney(movimiento.saldo)}</td>
                                        </tr>
                                    ))}

                                    {movimientosConSaldo.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                                                Sin movimientos en el rango.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
