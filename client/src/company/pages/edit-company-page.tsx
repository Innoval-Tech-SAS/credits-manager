import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { PageBreadcrumb } from '@ui/breadcrumb/components/page-breadcrumb';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { API_ROOT } from '../../shared/constants/api';
import { useCompany } from '../hooks/use-company';

export const EditCompanyPage: FC = () => {
    const { sendRequest } = useSendRequest();
    const { company, refresh } = useCompany();

    const [name, setName] = useState(company?.name ?? '');
    const [taxId, setTaxId] = useState(company?.taxId ?? '');
    const [address, setAddress] = useState(company?.address ?? '');
    const [phone, setPhone] = useState(company?.phone ?? '');
    const [email, setEmail] = useState(company?.email ?? '');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [logoError, setLogoError] = useState<string | null>(null);
    const [isUploadingLogo, setIsUploadingLogo] = useState(false);

    const handleLogoDrop = async (file: File) => {
        setLogoError(null);
        setIsUploadingLogo(true);

        try {
            const formData = new FormData();
            formData.append('logo', file);
            await sendRequest('/empresa/logo', { method: 'POST', body: formData });
            await refresh();
        } catch (caught) {
            setLogoError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsUploadingLogo(false);
        }
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setSuccess(false);
        setIsSubmitting(true);

        try {
            await sendRequest('/empresa', {
                method: 'PATCH',
                body: {
                    nombre_empresa: name,
                    nit: taxId,
                    direccion: address,
                    telefono: phone,
                    correo: email,
                },
            });

            await refresh();
            setSuccess(true);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-full flex-col pb-6">
            <PageBreadcrumb pageTitle="Empresa" />

            <div className="flex-1 rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm md:p-8 dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-8">
                    <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Datos de la empresa</h1>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Actualiza la información de tu empresa.</p>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="flex flex-col gap-8 xl:grid xl:grid-cols-2 xl:items-start xl:gap-x-12">
                        <section>
                            <div className="space-y-5">
                                {error && (
                                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                                        {error}
                                    </div>
                                )}
                                {success && (
                                    <div className="rounded-lg border border-success-500 bg-success-50 px-4 py-3 text-sm text-success-600 dark:border-success-800 dark:bg-success-500/10 dark:text-success-400">
                                        Configuración guardada correctamente.
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="name">Nombre de la empresa</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            value={name}
                                            onChange={(event) => setName(event.target.value)}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="tax-id">NIT</Label>
                                        <Input
                                            id="tax-id"
                                            name="tax-id"
                                            required
                                            value={taxId}
                                            onChange={(event) => setTaxId(event.target.value)}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="address">Dirección</Label>
                                    <Input
                                        id="address"
                                        name="address"
                                        required
                                        value={address}
                                        onChange={(event) => setAddress(event.target.value)}
                                    />
                                </div>

                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="phone">Teléfono</Label>
                                        <Input
                                            id="phone"
                                            name="phone"
                                            required
                                            value={phone}
                                            onChange={(event) => setPhone(event.target.value)}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="email">Correo</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                            value={email}
                                            onChange={(event) => setEmail(event.target.value)}
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section className="border-t border-gray-100 pt-8 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-12 dark:border-gray-800">
                            <Label>Logo</Label>

                            {logoError && (
                                <div className="mb-3 rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                                    {logoError}
                                </div>
                            )}

                            {company?.logoPath && (
                                <div className="mb-3 flex items-center gap-3">
                                    <img
                                        src={`${API_ROOT}/${company.logoPath}`}
                                        alt={company.name}
                                        className="max-h-14 max-w-40 rounded-lg border border-gray-200 p-1 dark:border-gray-800"
                                    />
                                    <span className="text-sm text-gray-500 dark:text-gray-400">Logo actual</span>
                                </div>
                            )}

                            {isUploadingLogo ? (
                                <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    <Spinner size="sm" />
                                    Subiendo logo…
                                </div>
                            ) : (
                                <Dropzone onFileAccepted={handleLogoDrop} hint="PNG, JPG, GIF o SVG — máx. 2MB" />
                            )}
                        </section>
                    </div>

                    <div className="mt-8 flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                        <Button
                            type="submit"
                            disabled={isSubmitting}
                            startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        >
                            {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
};
