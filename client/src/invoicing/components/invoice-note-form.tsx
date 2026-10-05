import type { FC } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { InvoiceLinesForm } from './invoice-lines-form';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { InvoiceNoteFormProps } from '../props/invoice-note-form-props';

type NoteKind = 'nota_credito' | 'nota_debito';

/**
 * Nota crédito: un valor opcional por cada línea de la factura (vacío = no se corrige esa línea).
 * Nota débito: líneas libres. Las reglas de saldo las aplica el servidor.
 */
export const InvoiceNoteForm: FC<InvoiceNoteFormProps> = ({ documento, onEmitida, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const tiposPermitidos: NoteKind[] = documento.tipo === 'factura' ? ['nota_credito', 'nota_debito'] : ['nota_debito'];
    const [tipo, setTipo] = useState<NoteKind>(tiposPermitidos[0]);
    const [motivo, setMotivo] = useState('');
    const [valoresCredito, setValoresCredito] = useState<Record<number, string>>({});
    const [lineasDebito, setLineasDebito] = useState<InvoiceLineDraft[]>([{ concepto: '', descripcion: '', valor: '' }]);

    const lineasFactura = documento.lineas ?? [];

    const emitir = async () => {
        const lineas =
            tipo === 'nota_credito'
                ? Object.entries(valoresCredito)
                      .filter(([, valor]) => valor.trim() !== '')
                      .map(([lineaId, valor]) => ({ linea_origen_id: Number(lineaId), valor }))
                : lineasDebito.map((linea) => ({ concepto: linea.concepto, descripcion: linea.descripcion, valor: linea.valor }));

        try {
            await sendRequest(`/facturacion/documentos/${documento.id}/notas`, { method: 'POST', body: { tipo, motivo, lineas } });
            setMotivo('');
            setValoresCredito({});
            setLineasDebito([{ concepto: '', descripcion: '', valor: '' }]);
            onEmitida();
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-6">
            {tiposPermitidos.length > 1 && (
                <div className="max-w-xs">
                    <Label htmlFor="tipo-nota">Tipo de nota</Label>
                    <Select
                        options={[
                            { value: 'nota_credito', label: 'Nota crédito' },
                            { value: 'nota_debito', label: 'Nota débito' },
                        ]}
                        defaultValue={tipo}
                        onChange={(valor) => setTipo(valor as NoteKind)}
                    />
                </div>
            )}

            <div>
                <Label htmlFor="motivo-nota">Motivo</Label>
                <Input id="motivo-nota" value={motivo} maxLength={500} onChange={(event) => setMotivo(event.target.value)} />
            </div>

            {tipo === 'nota_credito' ? (
                <div className="flex flex-col gap-4">
                    {lineasFactura.map((linea) => (
                        <div key={linea.id} className="grid grid-cols-1 gap-4 md:grid-cols-[2fr_1fr] md:items-end">
                            <div>
                                <Label htmlFor={`credito-${linea.id}`}>{`${linea.concepto} · ${linea.descripcion}`}</Label>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Valor original: {linea.valor}</p>
                            </div>
                            <Input
                                id={`credito-${linea.id}`}
                                type="number"
                                min="0.01"
                                step={0.01}
                                placeholder="A corregir"
                                value={valoresCredito[linea.id] ?? ''}
                                onChange={(event) => setValoresCredito({ ...valoresCredito, [linea.id]: event.target.value })}
                            />
                        </div>
                    ))}
                </div>
            ) : (
                <InvoiceLinesForm lineas={lineasDebito} onChange={setLineasDebito} />
            )}

            <div className="flex justify-end">
                <Button disabled={isLoading || motivo.trim() === ''} onClick={() => void emitir()}>
                    {isLoading ? 'Emitiendo…' : `Emitir ${tipo === 'nota_credito' ? 'nota crédito' : 'nota débito'}`}
                </Button>
            </div>
        </div>
    );
};
