import type { FC } from 'react';
import { Plus, Trash2 } from 'lucide-react';

import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import type { InvoiceLineDraft, InvoiceLinesFormProps } from '../props/invoice-lines-form-props';

const EMPTY_LINE: InvoiceLineDraft = { concepto: '', descripcion: '', valor: '' };

/** Líneas de una factura o de una nota débito. El concepto se digita: los nombres exactos los define DDA. */
export const InvoiceLinesForm: FC<InvoiceLinesFormProps> = ({ lineas, onChange, disabled = false, mostrarSoporteMora = false }) => {
    const actualizar = (indice: number, campo: keyof InvoiceLineDraft, valor: string) => {
        onChange(lineas.map((linea, i) => (i === indice ? { ...linea, [campo]: valor } : linea)));
    };

    const quitar = (indice: number) => {
        onChange(lineas.filter((_, i) => i !== indice));
    };

    return (
        <div className="flex flex-col gap-4">
            {lineas.map((linea, indice) => (
                <div key={indice} className="grid grid-cols-1 gap-4 md:grid-cols-[1fr_2fr_1fr_auto] md:items-end">
                    <div>
                        <Label htmlFor={`concepto-${indice}`}>Concepto</Label>
                        <Input
                            id={`concepto-${indice}`}
                            placeholder="INTERES.AMORT.ADM"
                            value={linea.concepto}
                            maxLength={60}
                            disabled={disabled}
                            onChange={(event) => actualizar(indice, 'concepto', event.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`descripcion-${indice}`}>Descripción</Label>
                        <Input
                            id={`descripcion-${indice}`}
                            value={linea.descripcion}
                            maxLength={255}
                            disabled={disabled}
                            onChange={(event) => actualizar(indice, 'descripcion', event.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor={`valor-${indice}`}>Valor</Label>
                        <Input
                            id={`valor-${indice}`}
                            type="number"
                            min="0.01"
                            step={0.01}
                            value={linea.valor}
                            disabled={disabled}
                            onChange={(event) => actualizar(indice, 'valor', event.target.value)}
                        />
                    </div>
                    {!disabled && (
                        <Button iconOnly variant="outline" startIcon={<Trash2 className="size-4" />} disabled={lineas.length === 1} onClick={() => quitar(indice)}>
                            Quitar línea
                        </Button>
                    )}
                    {mostrarSoporteMora && (
                        <div className="grid grid-cols-1 gap-4 md:col-span-4 md:grid-cols-3">
                            <div>
                                <Label htmlFor={`tasa-mora-${indice}`}>Tasa de mora (%)</Label>
                                <Input
                                    id={`tasa-mora-${indice}`}
                                    type="number"
                                    min="0"
                                    step={0.01}
                                    value={linea.tasa_mora ?? ''}
                                    disabled={disabled}
                                    onChange={(event) => actualizar(indice, 'tasa_mora', event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor={`dias-mora-${indice}`}>Días de mora</Label>
                                <Input
                                    id={`dias-mora-${indice}`}
                                    type="number"
                                    min="0"
                                    step={1}
                                    value={linea.dias_mora ?? ''}
                                    disabled={disabled}
                                    onChange={(event) => actualizar(indice, 'dias_mora', event.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor={`base-mora-${indice}`}>Base del cálculo</Label>
                                <Input
                                    id={`base-mora-${indice}`}
                                    type="number"
                                    min="0.01"
                                    step={0.01}
                                    value={linea.base_mora ?? ''}
                                    disabled={disabled}
                                    onChange={(event) => actualizar(indice, 'base_mora', event.target.value)}
                                />
                            </div>
                        </div>
                    )}
                </div>
            ))}

            {!disabled && (
                <div>
                    <Button variant="outline" startIcon={<Plus className="size-4" />} onClick={() => onChange([...lineas, { ...EMPTY_LINE }])}>
                        Agregar línea
                    </Button>
                </div>
            )}
        </div>
    );
};
