import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { NewImportPage } from './new-import-page';

const mocks = vi.hoisted(() => ({
    sendRequest: vi.fn(),
    download: vi.fn(),
    resource: { data: null as unknown },
}));

vi.mock('../../shared/hooks/use-send-request', () => ({
    useSendRequest: () => ({ sendRequest: mocks.sendRequest }),
}));

vi.mock('../../shared/hooks/use-download-file', () => ({
    useDownloadFile: () => ({ download: mocks.download, isDownloading: false }),
}));

vi.mock('../../shared/hooks/use-resource', () => ({
    useResource: () => ({ data: mocks.resource.data, refetch: () => {} }),
}));

vi.mock('@ui/breadcrumb/components/page-breadcrumb', () => ({
    PageBreadcrumb: () => null,
}));

/** El arrastre de archivos no se puede simular en jsdom: el componente real entrega un CSV de prueba al montar. */
vi.mock('@ui/dropzone/components/dropzone', async () => {
    const React = await import('react');

    return {
        Dropzone: ({ onFileAccepted }: { onFileAccepted: (file: File) => void }) => {
            React.useEffect(() => {
                onFileAccepted(new File(['Cédula;Nombre completo\n'], 'datos.csv', { type: 'text/csv' }));
            }, [onFileAccepted]);

            return null;
        },
    };
});

/** Selector nativo en lugar del dropdown del design system: mismo contrato `options`, `onChange(value)`. */
vi.mock('@ui/forms/components/select', () => ({
    Select: ({
        options,
        placeholder,
        defaultValue,
        onChange,
    }: {
        options: { value: string; label: string }[];
        placeholder?: string;
        defaultValue?: string;
        onChange: (value: string) => void;
    }) => (
        <select aria-label={placeholder} value={defaultValue ?? ''} onChange={(event) => onChange(event.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    ),
}));

function renderPagina(ruta: string): void {
    const envolver = (hijo: ReactNode) => <MemoryRouter initialEntries={[ruta]}>{hijo}</MemoryRouter>;

    render(
        envolver(
            <Routes>
                <Route path="/importaciones/nueva" element={<NewImportPage />} />
                <Route path="/importaciones/:id" element={<p>Pantalla de detalle</p>} />
            </Routes>,
        ),
    );
}

function botonValidar(): HTMLButtonElement {
    return screen.getByRole('button', { name: /Validar archivo|Subiendo/ }) as HTMLButtonElement;
}

describe('NewImportPage', () => {
    beforeEach(() => {
        mocks.sendRequest.mockReset();
        mocks.download.mockReset();
        mocks.resource.data = null;
    });

    it('envía los booleanos como 1 o 0 para que la validación del backend los acepte', async () => {
        mocks.sendRequest.mockResolvedValue({ importacion: { id: 7 } });
        renderPagina('/importaciones/nueva?tipo=clientes');

        fireEvent.click(screen.getByLabelText('Crear empleadores no encontrados'));
        await waitFor(() => expect(botonValidar().disabled).toBe(false));
        fireEvent.click(botonValidar());

        await waitFor(() => expect(mocks.sendRequest).toHaveBeenCalledTimes(1));
        const [ruta, opciones] = mocks.sendRequest.mock.calls[0] as [string, { method: string; body: FormData }];
        expect(ruta).toBe('/importaciones');
        expect(opciones.method).toBe('POST');
        expect(opciones.body.get('tipo')).toBe('clientes');
        expect(opciones.body.get('crear_empleadores')).toBe('1');
        expect(opciones.body.get('generar_asiento')).toBe('0');
        expect(opciones.body.get('cuenta_contrapartida_id')).toBeNull();
        expect(opciones.body.get('archivo')).toBeInstanceOf(File);
    });

    it('muestra un ejemplo visual del CSV para créditos con la columna de gastos administrativos', async () => {
        renderPagina('/importaciones/nueva?tipo=creditos');

        expect(await screen.findByText('Ejemplo de estructura')).toBeTruthy();
        expect(screen.getByText('Gastos administrativos (%)')).toBeTruthy();
        expect(screen.getByText('0,20')).toBeTruthy();
    });

    it('al subir navega a la pantalla de la importación creada', async () => {
        mocks.sendRequest.mockResolvedValue({ importacion: { id: 7 } });
        renderPagina('/importaciones/nueva?tipo=clientes');

        await waitFor(() => expect(botonValidar().disabled).toBe(false));
        fireEvent.click(botonValidar());

        expect(await screen.findByText('Pantalla de detalle')).toBeTruthy();
    });

    it('en créditos con asiento exige una cuenta de contrapartida imputable y la envía', async () => {
        mocks.resource.data = {
            cuentas: [
                { id: 5, codigo: '390505', nombre: 'Apertura', permite_movimiento: true, activa: true },
                { id: 6, codigo: '1', nombre: 'Título', permite_movimiento: false, activa: true },
            ],
        };
        mocks.sendRequest.mockResolvedValue({ importacion: { id: 8 } });
        renderPagina('/importaciones/nueva?tipo=creditos');

        fireEvent.click(screen.getByLabelText('Generar asientos contables'));
        expect(screen.getByText('390505 · Apertura')).toBeTruthy();
        expect(screen.queryByText('1 · Título')).toBeNull();

        await waitFor(() => expect(botonValidar().disabled).toBe(true));
        fireEvent.change(screen.getByLabelText('Elige la cuenta'), { target: { value: '5' } });
        await waitFor(() => expect(botonValidar().disabled).toBe(false));
        fireEvent.click(botonValidar());

        await waitFor(() => expect(mocks.sendRequest).toHaveBeenCalledTimes(1));
        const body = (mocks.sendRequest.mock.calls[0] as [string, { body: FormData }])[1].body;
        expect(body.get('generar_asiento')).toBe('1');
        expect(body.get('cuenta_contrapartida_id')).toBe('5');
    });

    it('muestra el motivo del rechazo del backend en lugar de navegar', async () => {
        mocks.sendRequest.mockRejectedValue(new Error('El archivo no coincide con la hoja Clientes.'));
        renderPagina('/importaciones/nueva?tipo=clientes');

        await waitFor(() => expect(botonValidar().disabled).toBe(false));
        fireEvent.click(botonValidar());

        expect(await screen.findByText('El archivo no coincide con la hoja Clientes.')).toBeTruthy();
        expect(screen.queryByText('Pantalla de detalle')).toBeNull();
    });
});
