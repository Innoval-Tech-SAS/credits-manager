import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Download, Upload } from 'lucide-react';

import { useDownloadFile } from '../../shared/hooks/use-download-file';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import type { ApiAccount } from '../../accounting/types/api-account';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Checkbox } from '@ui/forms/components/checkbox';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Label } from '@ui/forms/components/label';
import { Select } from '@ui/forms/components/select';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Spinner } from '@ui/spinner/components/spinner';
import { ImportSteps } from '../components/import-steps';
import {
    IMPORT_TYPE_OPTIONS,
    IMPORT_TYPES,
    IMPORT_TYPES_WITH_ACCOUNTING,
    IMPORT_TYPES_WITH_EMPLOYER_CHECK,
    IMPORT_TYPES_WITH_OFFSET_ACCOUNT,
    type ImportType,
} from '../constants/import-types';
import type { ApiImport } from '../types/api-import';

const STEPS = ['Preparar', 'Subir', 'Validar', 'Decidir', 'Escribir', 'Resultado'];

function esTipoImportable(valor: string): valor is ImportType {
    return (IMPORT_TYPES as readonly string[]).includes(valor);
}

export const NewImportPage: FC = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { sendRequest } = useSendRequest();
    const { download: descargarGuia, isDownloading } = useDownloadFile();

    const tipoInicial = searchParams.get('tipo') ?? '';
    const [tipo, setTipo] = useState<ImportType | ''>(esTipoImportable(tipoInicial) ? tipoInicial : '');
    const [archivo, setArchivo] = useState<File | null>(null);
    const [crearEmpleadores, setCrearEmpleadores] = useState(false);
    const [generarAsiento, setGenerarAsiento] = useState(false);
    const [cuentaContrapartidaId, setCuentaContrapartidaId] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const mostrarContrapartida = tipo !== '' && IMPORT_TYPES_WITH_OFFSET_ACCOUNT.includes(tipo) && generarAsiento;
    const { data: cuentas } = useResource<{ cuentas: ApiAccount[] }>(mostrarContrapartida ? '/contabilidad/plan-cuentas' : null);

    const opcionesCuentas = (cuentas?.cuentas ?? [])
        .filter((cuenta) => cuenta.permite_movimiento && cuenta.activa)
        .map((cuenta) => ({ value: String(cuenta.id), label: `${cuenta.codigo} · ${cuenta.nombre}` }));

    const handleDescargarGuia = async () => {
        if (tipo !== '') {
            await descargarGuia(`/importaciones/guias/${tipo}`, `guia-${tipo}.csv`);
        }
    };

    const handleTipoChange = (valor: string) => {
        setTipo(esTipoImportable(valor) ? valor : '');
        setCrearEmpleadores(false);
        setGenerarAsiento(false);
        setCuentaContrapartidaId('');
    };

    const handleSubir = async () => {
        if (tipo === '' || archivo === null) {
            setError('Elige la hoja y el archivo CSV.');
            return;
        }

        setError(null);
        setIsSubmitting(true);

        try {
            const formData = new FormData();
            formData.set('tipo', tipo);
            formData.set('archivo', archivo);
            formData.set('crear_empleadores', crearEmpleadores ? '1' : '0');
            formData.set('generar_asiento', generarAsiento ? '1' : '0');

            if (mostrarContrapartida && cuentaContrapartidaId !== '') {
                formData.set('cuenta_contrapartida_id', cuentaContrapartidaId);
            }

            const respuesta = await sendRequest<{ importacion: ApiImport }>('/importaciones', { method: 'POST', body: formData });
            navigate(`/importaciones/${respuesta.importacion.id}`, { replace: true });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="flex flex-col gap-6">
            <PageBreadcrumb pageTitle="Nueva importación" />

            <ImportSteps steps={STEPS} currentStep={tipo === '' ? 0 : 1} />

            {error && <Alert variant="danger" title="No se pudo subir el archivo" message={error} onDismiss={() => setError(null)} />}

            <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
                <h2 className="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">1. Preparar</h2>
                <p className="mb-5 text-sm text-gray-500 dark:text-gray-400">
                    Carga las hojas en este orden: clientes, créditos, plan de cuotas, pagos y saldos. Cada una depende de las anteriores. Guarda cada hoja como CSV,
                    borra la fila de ejemplo (la amarilla) y deja los datos reales desde la fila siguiente al encabezado.
                </p>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <Label htmlFor="tipo-importacion">Hoja a importar</Label>
                        <Select
                            options={IMPORT_TYPE_OPTIONS}
                            placeholder="Elige una hoja"
                            defaultValue={tipo}
                            onChange={handleTipoChange}
                        />
                    </div>

                    <div className="flex items-end">
                        <Button
                            variant="outline"
                            startIcon={<Download className="size-4" />}
                            disabled={tipo === '' || isDownloading}
                            onClick={() => void handleDescargarGuia()}
                        >
                            Descargar guía de la hoja
                        </Button>
                    </div>
                </div>
            </section>

            <section className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3 md:p-6">
                <h2 className="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">2. Subir</h2>

                <div className="flex flex-col gap-6">
                    <div>
                        <Label htmlFor="archivo-importacion">Archivo CSV</Label>
                        <Dropzone
                            onFileAccepted={setArchivo}
                            hint={archivo ? archivo.name : 'Arrastra o selecciona un archivo .csv (separador ; o , y codificación UTF-8 o Windows-1252)'}
                            accept={{ 'text/csv': ['.csv'] }}
                        />
                    </div>

                    <div className="flex flex-col gap-4">
                        {tipo !== '' && IMPORT_TYPES_WITH_EMPLOYER_CHECK.includes(tipo) && (
                            <Checkbox
                                id="crear-empleadores"
                                label="Crear empleadores no encontrados"
                                checked={crearEmpleadores}
                                onChange={setCrearEmpleadores}
                            />
                        )}

                        {tipo !== '' && IMPORT_TYPES_WITH_ACCOUNTING.includes(tipo) && (
                            <Checkbox id="generar-asiento" label="Generar asientos contables" checked={generarAsiento} onChange={setGenerarAsiento} />
                        )}

                        {mostrarContrapartida && (
                            <div className="max-w-xl">
                                <Label htmlFor="cuenta-contrapartida">Cuenta de contrapartida del asiento de apertura</Label>
                                <Select
                                    options={opcionesCuentas}
                                    placeholder="Elige la cuenta"
                                    defaultValue={cuentaContrapartidaId}
                                    onChange={setCuentaContrapartidaId}
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end">
                        <Button
                            startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : <Upload className="size-4" />}
                            disabled={isSubmitting || tipo === '' || archivo === null || (mostrarContrapartida && cuentaContrapartidaId === '')}
                            onClick={() => void handleSubir()}
                        >
                            {isSubmitting ? 'Subiendo…' : 'Validar archivo'}
                        </Button>
                    </div>
                </div>
            </section>
        </div>
    );
};
