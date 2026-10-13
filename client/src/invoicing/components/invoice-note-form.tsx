import type { FC } from 'react';
import { useState } from 'react';

import { usePermission } from '../../auth/hooks/use-permission';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { formatMoney } from '../../shared/utils/format-money';
import { Button } from '@ui/button/components/button';
import { DatePicker } from '@ui/forms/components/date-picker';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Modal } from '@ui/modal/components/modal';
import { Select } from '@ui/forms/components/select';
import { NOTE_CREDIT_REASON_OPTIONS, NOTE_DEBIT_REASON_OPTIONS } from '../constants/invoice-note-reasons';
import { InvoiceLinesForm } from './invoice-lines-form';
import type { InvoiceLineDraft } from '../props/invoice-lines-form-props';
import type { InvoiceNoteFormProps } from '../props/invoice-note-form-props';
import type { ApiInvoiceNoteCreditReason, ApiInvoiceNoteDebitReason } from '../types/api-invoice';

type NoteKind = 'nota_credito' | 'nota_debito';

/** Abre el formulario en un modal; se monta solo al abrir, para partir siempre de un estado limpio. */
export const InvoiceNoteForm: FC<InvoiceNoteFormProps> = ({ isOpen, documento, tipoInicial, onClose, onEmitida, onError }) => (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
        {isOpen && <FormularioNota documento={documento} tipoInicial={tipoInicial} onClose={onClose} onEmitida={onEmitida} onError={onError} />}
    </Modal>
);

type FormularioNotaProps = Omit<InvoiceNoteFormProps, 'isOpen'>;

/**
 * Nota crédito: un valor opcional por cada línea de la factura (vacío = no se corrige esa línea).
 * Nota débito: líneas libres. Las reglas de saldo las aplica el servidor.
 * El motivo sale de la lista cerrada del tipo; «otros» pide el detalle en texto libre. La nota débito también
 * acepta su propia fecha de vencimiento y, si el motivo es «intereses», el soporte del cálculo por línea.
 */
const FormularioNota: FC<FormularioNotaProps> = ({ documento, tipoInicial, onClose, onEmitida, onError }) => {
    const { sendRequest, isLoading } = useSendRequest();

    const puedeNotaCredito = usePermission('facturacion', 'emitir_nota_credito');
    const puedeNotaDebito = usePermission('facturacion', 'emitir_nota_debito');

    const tiposDelDocumento: NoteKind[] = documento.tipo === 'factura' ? ['nota_credito', 'nota_debito'] : ['nota_debito'];
    const tiposPermitidos = tiposDelDocumento.filter((tipoNota) => (tipoNota === 'nota_credito' ? puedeNotaCredito : puedeNotaDebito));
    const tipoPorDefecto = tipoInicial && tiposPermitidos.includes(tipoInicial) ? tipoInicial : tiposPermitidos[0];
    const [tipo, setTipo] = useState<NoteKind>(tipoPorDefecto);
    const [motivoCodigo, setMotivoCodigo] = useState<ApiInvoiceNoteCreditReason | ApiInvoiceNoteDebitReason>(
        tipoPorDefecto === 'nota_credito' ? NOTE_CREDIT_REASON_OPTIONS[0].value : NOTE_DEBIT_REASON_OPTIONS[0].value,
    );
    const [motivoDetalle, setMotivoDetalle] = useState('');
    const [fechaVencimiento, setFechaVencimiento] = useState('');
    const [valoresCredito, setValoresCredito] = useState<Record<number, string>>({});
    const [lineasDebito, setLineasDebito] = useState<InvoiceLineDraft[]>([{ concepto: '', descripcion: '', valor: '' }]);

    const lineasFactura = documento.lineas ?? [];
    const esOtros = motivoCodigo === 'otros';
    const esMora = tipo === 'nota_debito' && motivoCodigo === 'intereses';

    const totalCredito = Object.values(valoresCredito).reduce((acumulado, valor) => acumulado + (parseFloat(valor) || 0), 0);
    const totalDebito = lineasDebito.reduce((acumulado, linea) => acumulado + (parseFloat(linea.valor) || 0), 0);

    const cambiarTipo = (nuevoTipo: NoteKind) => {
        setTipo(nuevoTipo);
        setMotivoCodigo(nuevoTipo === 'nota_credito' ? NOTE_CREDIT_REASON_OPTIONS[0].value : NOTE_DEBIT_REASON_OPTIONS[0].value);
    };

    const emitir = async () => {
        const lineas =
            tipo === 'nota_credito'
                ? Object.entries(valoresCredito)
                      .filter(([, valor]) => valor.trim() !== '')
                      .map(([lineaId, valor]) => ({ linea_origen_id: Number(lineaId), valor }))
                : lineasDebito.map((linea) => ({
                      concepto: linea.concepto,
                      descripcion: linea.descripcion,
                      valor: linea.valor,
                      ...(esMora ? { tasa_mora: linea.tasa_mora, dias_mora: linea.dias_mora, base_mora: linea.base_mora } : {}),
                  }));

        try {
            await sendRequest(`/facturacion/documentos/${documento.id}/notas`, {
                method: 'POST',
                body: {
                    tipo,
                    motivo_codigo: motivoCodigo,
                    motivo_detalle: esOtros ? motivoDetalle : null,
                    fecha_vencimiento: tipo === 'nota_debito' ? fechaVencimiento || null : null,
                    lineas,
                },
            });
            onEmitida();
        } catch (caught) {
            onError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <div className="flex flex-col gap-6">
            <h3 className="pr-10 text-lg font-semibold text-gray-800 dark:text-white/90">Emitir nota</h3>

            {tiposPermitidos.length > 1 && (
                <div className="max-w-xs">
                    <Label htmlFor="tipo-nota">Tipo de nota</Label>
                    <Select
                        id="tipo-nota"
                        options={tiposPermitidos.map((tipoNota) => ({
                            value: tipoNota,
                            label: tipoNota === 'nota_credito' ? 'Nota crédito' : 'Nota débito',
                        }))}
                        defaultValue={tipo}
                        onChange={(valor) => cambiarTipo(valor as NoteKind)}
                    />
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <Label htmlFor="motivo-codigo-nota">Motivo</Label>
                    <Select
                        id="motivo-codigo-nota"
                        options={tipo === 'nota_credito' ? NOTE_CREDIT_REASON_OPTIONS : NOTE_DEBIT_REASON_OPTIONS}
                        defaultValue={motivoCodigo}
                        onChange={(valor) => setMotivoCodigo(valor as ApiInvoiceNoteCreditReason | ApiInvoiceNoteDebitReason)}
                    />
                </div>
                {tipo === 'nota_debito' && (
                    <div>
                        <Label htmlFor="fecha-vencimiento-nota">Fecha de vencimiento (opcional)</Label>
                        <DatePicker id="fecha-vencimiento-nota" value={fechaVencimiento} onChange={setFechaVencimiento} />
                    </div>
                )}
            </div>

            {esOtros && (
                <div>
                    <Label htmlFor="motivo-detalle-nota">Detalle del motivo</Label>
                    <Input id="motivo-detalle-nota" value={motivoDetalle} maxLength={500} onChange={(event) => setMotivoDetalle(event.target.value)} />
                </div>
            )}

            {tipo === 'nota_credito' ? (
                <div className="flex flex-col gap-4">
                    {lineasFactura.map((linea) => (
                        <div key={linea.id} className="grid grid-cols-1 gap-4 md:grid-cols-[2fr_1fr] md:items-end">
                            <div>
                                <Label htmlFor={`credito-${linea.id}`}>{`${linea.concepto} · ${linea.descripcion}`}</Label>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Valor original: {formatMoney(linea.valor)}</p>
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

                    {totalCredito > 0 && (
                        <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <span className="text-sm text-gray-500 dark:text-gray-400">Total nota crédito</span>
                            <span className="text-base font-semibold text-gray-800 dark:text-white/90">{formatMoney(totalCredito)}</span>
                        </div>
                    )}
                </div>
            ) : (
                <>
                    <InvoiceLinesForm lineas={lineasDebito} onChange={setLineasDebito} mostrarSoporteMora={esMora} />

                    {totalDebito > 0 && (
                        <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                            <span className="text-sm text-gray-500 dark:text-gray-400">Total nota débito</span>
                            <span className="text-base font-semibold text-gray-800 dark:text-white/90">{formatMoney(totalDebito)}</span>
                        </div>
                    )}
                </>
            )}

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button variant="outline" onClick={onClose} disabled={isLoading}>
                    Cancelar
                </Button>
                <Button disabled={isLoading || (esOtros && motivoDetalle.trim() === '')} onClick={() => void emitir()}>
                    {isLoading ? 'Emitiendo…' : `Emitir ${tipo === 'nota_credito' ? 'nota crédito' : 'nota débito'}`}
                </Button>
            </div>
        </div>
    );
};
