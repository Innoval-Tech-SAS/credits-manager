import type { FC } from 'react';

import { PERSON_TYPE_LABELS } from '../../third-parties/constants/person-type-choices';
import { Alert } from '@ui/alert/components/alert';
import type { ThirdPartyFiscalSummaryProps } from '../props/third-party-fiscal-summary-props';

/** Mismos campos que exige `DocumentoFacturacionService::exigirTerceroFiscalCompleto` antes de emitir. */
function datosFaltantes(tercero: ThirdPartyFiscalSummaryProps['tercero']): string[] {
    const faltantes: string[] = [];

    if (!tercero.nombre) faltantes.push('nombre');
    if (!tercero.numero_documento) faltantes.push('número de documento');
    if (!tercero.direccion) faltantes.push('dirección');
    if (!tercero.correo) faltantes.push('correo');
    if (tercero.tipo_persona === 'juridica' && !tercero.digito_verificacion) faltantes.push('dígito de verificación');

    return faltantes;
}

/** Resumen fiscal del tercero del cliente: lo que el motor de IVA/retenciones usa y lo que exige la emisión. */
export const ThirdPartyFiscalSummary: FC<ThirdPartyFiscalSummaryProps> = ({ tercero }) => {
    const faltantes = datosFaltantes(tercero);

    return (
        <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
            <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Datos fiscales del cliente</h3>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Tipo de persona</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">{PERSON_TYPE_LABELS[tercero.tipo_persona]}</p>
                </div>
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Documento</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">
                        {tercero.tipo_documento.toUpperCase()} {tercero.numero_documento}
                        {tercero.digito_verificacion && `-${tercero.digito_verificacion}`}
                    </p>
                </div>
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Régimen tributario</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">{tercero.regimen_tributario ?? '—'}</p>
                </div>
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Responsabilidades</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">
                        {[tercero.responsable_iva && 'Responsable de IVA', tercero.autorretenedor && 'Autorretenedor'].filter(Boolean).join(' · ') || 'Ninguna'}
                    </p>
                </div>
                <div className="wrap-break-word">
                    <p className="text-sm text-gray-500 dark:text-gray-400">Correo</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">{tercero.correo ?? '—'}</p>
                </div>
                <div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Dirección</p>
                    <p className="font-medium text-gray-800 dark:text-white/90">{tercero.direccion ?? '—'}</p>
                </div>
            </div>

            {faltantes.length > 0 && (
                <div className="mt-4">
                    <Alert
                        variant="warning"
                        title="Datos fiscales incompletos"
                        message={`Falta ${faltantes.join(', ')}. El borrador se puede guardar, pero la factura no se podrá emitir hasta completarlos en el cliente.`}
                    />
                </div>
            )}
        </div>
    );
};
