import type { FC } from 'react';
import { useState } from 'react';
import { Trash2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiCrossDocument } from '../types/api-cross-document';
import type { VoucherFormLineRowProps } from '../props/voucher-form-line-row-props';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Dropdown } from '@ui/dropdown/components/dropdown';
import { DropdownItem } from '@ui/dropdown/components/dropdown-item';
import { Input } from '@ui/forms/components/input';
import { Select } from '@ui/forms/components/select';

export const VoucherFormLineRow: FC<VoucherFormLineRowProps> = ({
    linea,
    cuentas,
    cuentaOptions,
    centroCostoOptions,
    terceroComprobante,
    puedeEliminar,
    onActualizar,
    onEliminar,
}) => {
    const cuenta = cuentas.find((item) => String(item.id) === linea.cuenta_id);
    // Al contabilizar, el backend usa el tercero del encabezado cuando la línea no trae uno.
    const tercero = linea.tercero ?? terceroComprobante;

    // El backend decide qué cuentas manejan documento cruce y qué saldos (vencidos, abonados, anticipos) se pueden cruzar.
    const { data: documentosData } = useResource<{ data: ApiCrossDocument[] }>(
        cuenta && tercero ? `/contabilidad/comprobantes/documentos-cruce?cuenta_id=${cuenta.id}&tercero_id=${tercero.id}` : null,
    );
    const filtro = linea.documento_cruce.trim().toLowerCase();
    const documentos = (documentosData?.data ?? []).filter((documento) => documento.numero_documento.toLowerCase().includes(filtro));
    const [docCruceAbierto, setDocCruceAbierto] = useState(false);

    return (
        <tr className="border-b border-gray-100 dark:border-gray-800">
            <td className="px-4 py-4">
                <Select
                    options={cuentaOptions}
                    placeholder="--"
                    defaultValue={linea.cuenta_id}
                    onChange={(value) => onActualizar({ cuenta_id: value })}
                />
            </td>
            <td className="px-4 py-4">
                <Select
                    options={centroCostoOptions}
                    placeholder="--"
                    defaultValue={linea.centro_costo_id}
                    onChange={(value) => onActualizar({ centro_costo_id: value })}
                />
            </td>
            <td className="min-w-50 px-4 py-4">
                <ThirdPartySelector
                    id={`tercero-${linea.key}`}
                    selected={linea.tercero}
                    onSelect={(thirdParty) => onActualizar({ tercero: thirdParty })}
                    onClear={() => onActualizar({ tercero: null })}
                />
            </td>
            <td className="min-w-40 px-4 py-4">
                <div className="relative">
                    <Input
                        value={linea.documento_cruce}
                        onFocus={() => setDocCruceAbierto(true)}
                        onChange={(event) => onActualizar({ documento_cruce: event.target.value })}
                    />

                    {/* Deja escribir un número nuevo (documento que se crea en esta línea) y a la vez elegir uno pendiente (documento que se cruza/abona). */}
                    <Dropdown
                        isOpen={docCruceAbierto && documentos.length > 0}
                        onClose={() => setDocCruceAbierto(false)}
                        className="max-h-64 w-full min-w-72 overflow-y-auto custom-scrollbar left-0"
                    >
                        {documentos.map((documento) => (
                            <DropdownItem
                                key={documento.id}
                                onClick={() => {
                                    // Cruzar/abonar un documento es el movimiento contrario a su naturaleza: una cuenta débito (CxC) se abona por el crédito, y viceversa.
                                    const campoSaldo = cuenta?.naturaleza === 'debito' ? 'credito' : 'debito';
                                    const campoContrario = campoSaldo === 'debito' ? 'credito' : 'debito';
                                    onActualizar({
                                        documento_cruce: documento.numero_documento,
                                        [campoSaldo]: documento.saldo,
                                        [campoContrario]: '',
                                    });
                                    setDocCruceAbierto(false);
                                }}
                            >
                                <span className="block font-medium text-gray-800 dark:text-white/90">
                                    {documento.numero_documento} · {formatMoney(documento.saldo)}
                                </span>
                                <span className="block text-xs text-gray-500 dark:text-gray-400">
                                    {documento.tipo_documento}
                                    {documento.fecha_vencimiento && ` · vence ${documento.fecha_vencimiento}`}
                                </span>
                            </DropdownItem>
                        ))}
                    </Dropdown>
                </div>
            </td>
            <td className="px-4 py-4">
                <DatePicker
                    id={`vencimiento-${linea.key}`}
                    value={linea.fecha_vencimiento}
                    onChange={(value) => onActualizar({ fecha_vencimiento: value })}
                    appendToBody
                />
            </td>
            <td className="px-4 py-4">
                <Input value={linea.descripcion} onChange={(event) => onActualizar({ descripcion: event.target.value })} />
            </td>
            <td className="px-4 py-4">
                <Input type="number" step={0.01} value={linea.debito} onChange={(event) => onActualizar({ debito: event.target.value })} />
            </td>
            <td className="px-4 py-4">
                <Input type="number" step={0.01} value={linea.credito} onChange={(event) => onActualizar({ credito: event.target.value })} />
            </td>
            <td className="px-4 py-4">
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
