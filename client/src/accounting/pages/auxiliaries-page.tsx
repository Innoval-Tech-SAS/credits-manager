import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatDateEs } from '../../shared/utils/format-date-es';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiThirdParty } from '../../third-parties/types/api-third-party';
import { ReportExportButton } from '../components/report-export-button';
import type { ApiAccount } from '../types/api-account';
import type { ApiAuxiliary } from '../types/api-auxiliary';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { Spinner } from '@ui/spinner/components/spinner';

type Modo = 'cuenta' | 'tercero';

const MODOS: { value: Modo; label: string }[] = [
    { value: 'cuenta', label: 'Por cuenta' },
    { value: 'tercero', label: 'Por tercero' },
];

const CABECERAS = ['Fecha', 'Comprobante', 'Detalle', 'Tercero', 'Débito', 'Crédito', 'Saldo'];

/** Auxiliar por cuenta o por tercero. Cada cuenta muestra su saldo inicial, sus movimientos y su saldo final. */
export const AuxiliariesPage: FC = () => {
    const [modo, setModo] = useState<Modo>('cuenta');
    const [desde, setDesde] = useState(() => `${new Date().getFullYear()}-01-01`);
    const [hasta, setHasta] = useState(() => new Date().toISOString().slice(0, 10));
    const [cuentaId, setCuentaId] = useState('');
    const [tercero, setTercero] = useState<ApiThirdParty | null>(null);

    const { data: cuentasRespuesta } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');
    const cuentaOptions = (cuentasRespuesta?.cuentas ?? [])
        .filter((cuenta) => cuenta.permite_movimiento && cuenta.activa)
        .map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} - ${cuenta.nombre}` }));

    const filtros: Record<string, string> = {
        desde,
        hasta,
        ...(modo === 'cuenta' && cuentaId ? { cuenta_id: cuentaId } : {}),
        ...(modo === 'tercero' && tercero ? { tercero_id: String(tercero.id) } : {}),
    };
    const tieneFiltroEspecifico = 'cuenta_id' in filtros || 'tercero_id' in filtros;
    const endpoint = tieneFiltroEspecifico ? `/contabilidad/auxiliares?${new URLSearchParams(filtros).toString()}` : null;

    const { data, isLoading, error } = useResource<ApiAuxiliary>(endpoint);
    const grupos = data?.grupos ?? [];

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Auxiliares" />

            <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div className="inline-flex rounded-lg border border-gray-200 p-1 dark:border-gray-800" role="tablist">
                    {MODOS.map((opcion) => (
                        <button
                            key={opcion.value}
                            type="button"
                            role="tab"
                            aria-selected={modo === opcion.value}
                            onClick={() => setModo(opcion.value)}
                            className={`rounded-md px-4 py-2 text-sm font-medium transition ${
                                modo === opcion.value
                                    ? 'bg-brand-500 text-white'
                                    : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90'
                            }`}
                        >
                            {opcion.label}
                        </button>
                    ))}
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                    {modo === 'cuenta' ? (
                        <div className="md:col-span-1">
                            <Label htmlFor="cuenta">Cuenta</Label>
                            <Select options={cuentaOptions} defaultValue={cuentaId} onChange={setCuentaId} />
                        </div>
                    ) : (
                        <div className="md:col-span-1">
                            <Label htmlFor="tercero">Tercero</Label>
                            <ThirdPartySelector id="tercero" selected={tercero} onSelect={setTercero} />
                        </div>
                    )}

                    <div>
                        <Label htmlFor="desde">Desde</Label>
                        <DatePicker id="desde" value={desde} onChange={setDesde} />
                    </div>
                    <div>
                        <Label htmlFor="hasta">Hasta</Label>
                        <DatePicker id="hasta" value={hasta} onChange={setHasta} />
                    </div>
                </div>

                <div className="mt-6">
                    <ReportExportButton
                        endpoint="/contabilidad/auxiliares/exportar"
                        exportPermissions={['informes_contables.exportar_auxiliares']}
                        pdfEndpoint="/contabilidad/auxiliares/pdf"
                        pdfFilename="auxiliar.pdf"
                        filters={filtros}
                        disabled={!endpoint}
                    />
                </div>
            </div>

            {!endpoint && <Alert variant="info" title="Elige un filtro" message="Selecciona una cuenta o un tercero para ver su auxiliar." />}
            {error && <Alert variant="danger" title="No se pudo consultar" message={error} />}
            {isLoading && (
                <div className="flex justify-center py-12">
                    <Spinner size="lg" />
                </div>
            )}

            {endpoint && !isLoading && grupos.length === 0 && !error && (
                <Alert variant="info" title="Sin movimientos" message="No hay movimientos contabilizados para este filtro en el período." />
            )}

            {grupos.map((grupo) => (
                <section key={grupo.cuenta.id} className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                        <h3 className="text-base font-semibold text-gray-800 dark:text-white/90">
                            {grupo.cuenta.codigo} {grupo.cuenta.nombre}
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">Saldo inicial: {formatMoney(grupo.saldo_inicial)}</p>
                    </div>

                    <div className="custom-scrollbar overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-gray-800">
                                    {CABECERAS.map((cabecera) => (
                                        <th key={cabecera} className="px-3 py-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                                            {cabecera}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {grupo.movimientos.map((movimiento, indice) => (
                                    <tr key={`${movimiento.comprobante}-${indice}`} className="border-b border-gray-100 dark:border-gray-800">
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{formatDateEs(movimiento.fecha)}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{movimiento.comprobante}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{movimiento.descripcion ?? '—'}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{movimiento.tercero ?? '—'}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{formatMoney(movimiento.debito)}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{formatMoney(movimiento.credito)}</td>
                                        <td className="px-3 py-3 text-gray-700 dark:text-gray-300">{formatMoney(movimiento.saldo)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="font-semibold text-gray-800 dark:text-white/90">
                                    <td colSpan={4} className="px-3 py-3">
                                        Totales del período
                                    </td>
                                    <td className="px-3 py-3">{formatMoney(grupo.total_debito)}</td>
                                    <td className="px-3 py-3">{formatMoney(grupo.total_credito)}</td>
                                    <td className="px-3 py-3">{formatMoney(grupo.saldo_final)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            ))}
        </div>
    );
};
