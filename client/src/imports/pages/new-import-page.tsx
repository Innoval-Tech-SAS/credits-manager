import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router';
import { Download, FileText, Upload } from 'lucide-react';

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
import { CsvExampleCard } from '../components/csv-example-card';
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

const IMPORT_EXAMPLE_BY_TYPE: Record<ImportType, { columns: { key: string; label: string }[]; example: Record<string, string> }> = {
    clientes: {
        columns: [
            { key: 'cedula', label: 'Cédula' },
            { key: 'nombre', label: 'Nombre completo' },
            { key: 'telefono', label: 'Teléfono' },
        ],
        example: { cedula: '53125435', nombre: 'Adriana Daza Mesa', telefono: '3001234567' },
    },
    creditos: {
        columns: [
            { key: 'codigo', label: 'Código de crédito' },
            { key: 'cedula', label: 'Cédula cliente' },
            { key: 'fecha_desembolso', label: 'Fecha de desembolso' },
            { key: 'valor', label: 'Valor desembolsado (capital)' },
            { key: 'tasa', label: 'Tasa de interés mensual (%)' },
            { key: 'plazo', label: 'Plazo (# cuotas)' },
            { key: 'periodicidad', label: 'Periodicidad (Mensual/Quincenal)' },
            { key: 'cuota', label: 'Cuota fija' },
            { key: 'gastos_administracion', label: 'Gastos administrativos (%)' },
            { key: 'es_refinanciacion', label: '¿Es refinanciación? (Sí/No)' },
        ],
        example: {
            codigo: 'CR-2024-001',
            cedula: '53125435',
            fecha_desembolso: '01/10/2024',
            valor: '2000000',
            tasa: '2,5',
            plazo: '4',
            periodicidad: 'mensual',
            cuota: '',
            gastos_administracion: '0,20',
            es_refinanciacion: 'No',
        },
    },
    plan_cuotas: {
        columns: [
            { key: 'codigo', label: 'Código de crédito' },
            { key: 'numero', label: 'Número de cuota' },
            { key: 'fecha', label: 'Fecha de vencimiento' },
            { key: 'total', label: 'Valor total cuota' },
        ],
        example: { codigo: 'CR-2024-001', numero: '1', fecha: '01/11/2024', total: '520000' },
    },
    pagos: {
        columns: [
            { key: 'codigo', label: 'Código de crédito' },
            { key: 'fecha', label: 'Fecha de pago' },
            { key: 'valor', label: 'Valor pagado' },
            { key: 'tipo', label: 'Tipo' },
        ],
        example: { codigo: 'CR-2024-001', fecha: '01/11/2024', valor: '520000', tipo: 'Cuota completa' },
    },
    saldos_iniciales: {
        columns: [
            { key: 'fecha_corte', label: 'Fecha de corte' },
            { key: 'cuenta', label: 'Cuenta PUC' },
            { key: 'debito', label: 'Saldo débito' },
            { key: 'credito', label: 'Saldo crédito' },
        ],
        example: { fecha_corte: '01/10/2024', cuenta: '133005', debito: '45000000', credito: '' },
    },
    plan_cuentas: {
        columns: [
            { key: 'codigo', label: 'Código' },
            { key: 'nombre', label: 'Nombre' },
            { key: 'naturaleza', label: 'Naturaleza' },
        ],
        example: { codigo: '133005', nombre: 'Cartera de créditos', naturaleza: 'Deudora' },
    },
    terceros: {
        columns: [
            { key: 'tipo', label: 'Tipo' },
            { key: 'tipo_persona', label: 'Tipo de persona' },
            { key: 'numero_documento', label: 'Número de documento' },
        ],
        example: { tipo: 'Natural', tipo_persona: 'Persona natural', numero_documento: '53125435' },
    },
    gastos: {
        columns: [
            { key: 'fecha', label: 'Fecha del gasto' },
            { key: 'numero_documento', label: 'Número de documento' },
            { key: 'tipo_gasto', label: 'Tipo de gasto' },
            { key: 'valor_bruto', label: 'Valor bruto' },
        ],
        example: { fecha: '01/10/2024', numero_documento: 'FAC-001', tipo_gasto: 'Servicios', valor_bruto: '3500000' },
    },
    actividades_economicas: {
        columns: [
            { key: 'codigo', label: 'Código CIIU' },
            { key: 'nombre', label: 'Actividad económica' },
        ],
        example: { codigo: '4722', nombre: 'Comercio al por menor' },
    },
    valores_uvt: {
        columns: [
            { key: 'anio', label: 'Año' },
            { key: 'valor', label: 'Valor UVT' },
        ],
        example: { anio: '2024', valor: '46700' },
    },
    tarifas_reteica: {
        columns: [
            { key: 'municipio', label: 'Municipio' },
            { key: 'actividad_economica', label: 'Actividad económica' },
            { key: 'tarifa', label: 'Tarifa (%)' },
        ],
        example: { municipio: '11001', actividad_economica: '4722', tarifa: '1,09' },
    },
};

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

            {tipo !== '' && (
                <CsvExampleCard
                    icon={<FileText className="size-4" />}
                    title="Ejemplo de estructura"
                    subtitle={
                        tipo === 'creditos'
                            ? 'La columna del CSV se escribe como gastos_administracion y conserva el porcentaje en la fila de ejemplo.'
                            : 'Estructura esperada del archivo CSV para esta hoja.'
                    }
                    columns={IMPORT_EXAMPLE_BY_TYPE[tipo].columns}
                    example={IMPORT_EXAMPLE_BY_TYPE[tipo].example}
                />
            )}

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
