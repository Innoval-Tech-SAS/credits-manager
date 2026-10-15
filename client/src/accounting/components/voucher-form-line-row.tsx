import type { FC } from 'react';
import { Trash2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiCarteraDocument } from '../types/api-cartera-document';
import type { VoucherFormLineRowProps } from '../props/voucher-form-line-row-props';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Select } from '@ui/forms/components/select';

export const VoucherFormLineRow: FC<VoucherFormLineRowProps> = ({
    linea,
    cuentas,
    cuentaOptions,
    centroCostoOptions,
    puedeEliminar,
    onActualizar,
    onEliminar,
}) => {
    const cuenta = cuentas.find((item) => String(item.id) === linea.cuenta_id);
    const tercero = linea.tercero;
    // La dirección del documento se deriva de `tipo` igual que en `ComprobanteService::procesarDocumentosTerceroAlContabilizar`.
    const naturalezaDocumento = cuenta?.tipo === 'activo' ? 'por_cobrar' : cuenta?.tipo === 'pasivo' ? 'por_pagar' : null;

    const { data: pendientesData } = useResource<{ data: ApiCarteraDocument[] }>(
        cuenta?.exige_documento_cruce && naturalezaDocumento && tercero
            ? `/cartera-terceros?naturaleza=${naturalezaDocumento}&tercero_id=${tercero.id}&plan_cuenta_id=${cuenta.id}&estado=pendiente&per_page=10`
            : null,
    );
    const pendientes = pendientesData?.data ?? [];

    return (
        <tr className="border-b border-gray-100 dark:border-gray-800">
            <td className="px-4 py-2">
                <Select
                    options={cuentaOptions}
                    placeholder="--"
                    defaultValue={linea.cuenta_id}
                    onChange={(value) => onActualizar({ cuenta_id: value })}
                />
            </td>
            <td className="px-4 py-2">
                <Select
                    options={centroCostoOptions}
                    placeholder="--"
                    defaultValue={linea.centro_costo_id}
                    onChange={(value) => onActualizar({ centro_costo_id: value })}
                />
            </td>
            <td className="min-w-50 px-4 py-2">
                <ThirdPartySelector
                    id={`tercero-${linea.key}`}
                    selected={linea.tercero}
                    onSelect={(thirdParty) => onActualizar({ tercero: thirdParty })}
                    onClear={() => onActualizar({ tercero: null })}
                />
            </td>
            <td className="min-w-40 px-4 py-2">
                <Input value={linea.documento_cruce} onChange={(event) => onActualizar({ documento_cruce: event.target.value })} />

                {pendientes.length > 0 && (
                    <div className="mt-1.5 flex flex-wrap gap-1">
                        {pendientes.map((documento) => (
                            <button
                                key={documento.id}
                                type="button"
                                onClick={() => onActualizar({ documento_cruce: documento.numero_documento })}
                                title="Usar como documento cruce"
                                className="rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-600 hover:border-brand-300 hover:bg-brand-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-brand-800 dark:hover:bg-brand-500/10"
                            >
                                {documento.numero_documento} · {formatMoney(documento.saldo)}
                            </button>
                        ))}
                    </div>
                )}
            </td>
            <td className="px-4 py-2">
                <DatePicker
                    id={`vencimiento-${linea.key}`}
                    value={linea.fecha_vencimiento}
                    onChange={(value) => onActualizar({ fecha_vencimiento: value })}
                    appendToBody
                />
            </td>
            <td className="px-4 py-2">
                <Input value={linea.descripcion} onChange={(event) => onActualizar({ descripcion: event.target.value })} />
            </td>
            <td className="px-4 py-2">
                <Input type="number" step={0.01} value={linea.debito} onChange={(event) => onActualizar({ debito: event.target.value })} />
            </td>
            <td className="px-4 py-2">
                <Input type="number" step={0.01} value={linea.credito} onChange={(event) => onActualizar({ credito: event.target.value })} />
            </td>
            <td className="px-4 py-2">
                <button
                    type="button"
                    onClick={onEliminar}
                    disabled={!puedeEliminar}
                    className="text-gray-500 hover:text-error-500 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-400 dark:hover:text-error-500"
                >
                    <Trash2 className="size-4" />
                </button>
            </td>
        </tr>
    );
};
