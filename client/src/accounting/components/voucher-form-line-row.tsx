import type { FC } from 'react';
import { useState } from 'react';
import { Trash2 } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import { ThirdPartySelector } from '../../third-parties/components/third-party-selector';
import type { ApiCarteraDocument } from '../types/api-cartera-document';
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
                        isOpen={docCruceAbierto && pendientes.length > 0}
                        onClose={() => setDocCruceAbierto(false)}
                        className="max-h-64 w-full overflow-y-auto custom-scrollbar left-0"
                    >
                        {pendientes.map((documento) => (
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
                                {documento.numero_documento} · {formatMoney(documento.saldo)}
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
