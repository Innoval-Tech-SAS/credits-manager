import type { FC } from 'react';
import { useState } from 'react';

import { useResource } from '../../shared/hooks/use-resource';
import { Alert } from '@ui/alert/components/alert';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ConceptTaxSettingsForm } from '../components/concept-tax-settings-form';
import { CONCEPT_TYPE_LABELS } from '../constants/concept-types';
import type { ApiAccount } from '../../accounting/types/api-account';
import type { ApiConceptTaxSettings } from '../types/api-invoice';

/** Parametrización fiscal de los 4 conceptos de factura (capital, interés corriente, interés de mora, otros gastos). */
export const ConceptTaxSettingsPage: FC = () => {
    const { data, error } = useResource<{ parametrizaciones: ApiConceptTaxSettings[] }>('/facturacion/parametrizacion-conceptos');
    const { data: cuentasData, isLoading: cargandoCuentas } = useResource<{ cuentas: ApiAccount[] }>('/contabilidad/plan-cuentas');

    const [mensaje, setMensaje] = useState<string | null>(null);

    if (error) {
        return <Alert variant="danger" title="No se pudo cargar la parametrización" message={error} />;
    }

    if (!data || cargandoCuentas) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    const cuentas = (cuentasData?.cuentas ?? []).filter((cuenta) => cuenta.permite_movimiento);

    return (
        <div className="flex min-h-full flex-col gap-6 pb-6">
            <PageBreadcrumb pageTitle="Parametrización fiscal de conceptos" />

            {mensaje && <Alert variant="success" title="Listo" message={mensaje} onDismiss={() => setMensaje(null)} />}

            <div className="flex flex-col gap-4">
                {data.parametrizaciones.map((parametrizacion) => (
                    <div key={parametrizacion.tipo_concepto} className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                        <h3 className="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">{CONCEPT_TYPE_LABELS[parametrizacion.tipo_concepto]}</h3>
                        <ConceptTaxSettingsForm
                            parametrizacion={parametrizacion}
                            cuentas={cuentas}
                            onSaved={() => setMensaje(`Parametrización de «${CONCEPT_TYPE_LABELS[parametrizacion.tipo_concepto]}» guardada.`)}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
};
