import type { FC, SubmitEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { Navigate, useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Dropzone } from '@ui/dropzone/components/dropzone';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { ArrowRight, ChevronLeft } from 'lucide-react';
import { OnboardingStepIndicator } from '../components/onboarding-step-indicator';
import { useCompany } from '../hooks/use-company';

const TOTAL_STEPS = 3;

export const OnboardingCompanyPage: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();
    const { company, isLoading: isCompanyLoading, refresh } = useCompany();

    const [step, setStep] = useState(1);

    const [name, setName] = useState('');
    const [taxId, setTaxId] = useState('');
    const [address, setAddress] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');
    const [logo, setLogo] = useState<File | null>(null);
    const logoPreviewUrl = useMemo(() => (logo ? URL.createObjectURL(logo) : null), [logo]);

    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        return () => {
            if (logoPreviewUrl) {
                URL.revokeObjectURL(logoPreviewUrl);
            }
        };
    }, [logoPreviewUrl]);

    if (!isCompanyLoading && company) {
        return <Navigate to="/" replace />;
    }

    const goToNextStep = () => {
        if (step === 1 && (!name.trim() || !taxId.trim())) {
            setError('Completa el nombre y el NIT de la empresa.');
            return;
        }

        if (step === 2 && (!address.trim() || !phone.trim() || !email.trim())) {
            setError('Completa la dirección, el teléfono y el correo.');
            return;
        }

        setError(null);
        setStep((current) => Math.min(current + 1, TOTAL_STEPS));
    };

    const goToPreviousStep = () => {
        setError(null);
        setStep((current) => Math.max(current - 1, 1));
    };

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (step !== TOTAL_STEPS) {
            goToNextStep();
            return;
        }

        setError(null);
        setIsSubmitting(true);

        try {
            await sendRequest('/empresa', {
                method: 'POST',
                body: {
                    nombre_empresa: name,
                    nit: taxId,
                    direccion: address,
                    telefono: phone,
                    correo: email,
                },
            });

            if (logo) {
                const formData = new FormData();
                formData.append('logo', logo);
                await sendRequest('/empresa/logo', { method: 'POST', body: formData });
            }

            await refresh();
            navigate('/', { replace: true });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center overflow-y-auto bg-gray-50 px-4 py-10 dark:bg-gray-900">
            <div className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-6 text-center">
                    <h1 className="text-title-sm font-semibold text-gray-800 dark:text-white/90">Configura tu empresa</h1>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Antes de continuar, ingresa los datos de tu empresa.
                    </p>
                </div>

                <OnboardingStepIndicator currentStep={step} />

                <form onSubmit={handleSubmit}>
                    <div className="space-y-5">
                        {error && (
                            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                                {error}
                            </div>
                        )}

                        {step === 1 && (
                            <>
                                <div>
                                    <Label htmlFor="name">Nombre de la empresa</Label>
                                    <Input id="name" name="name" required value={name} onChange={(event) => setName(event.target.value)} />
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
                            </>
                        )}

                        {step === 2 && (
                            <>
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
                            </>
                        )}

                        {step === 3 && (
                            <div>
                                <Label>Logo (opcional)</Label>
                                <Dropzone onFileAccepted={setLogo} />
                                {logo && logoPreviewUrl && (
                                    <div className="mt-3 flex items-center gap-3">
                                        <img
                                            src={logoPreviewUrl}
                                            alt="Vista previa del logo"
                                            className="max-h-14 max-w-40 rounded-lg border border-gray-200 p-1 dark:border-gray-800"
                                        />
                                        <span className="text-sm text-gray-500 dark:text-gray-400">{logo.name}</span>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex gap-3">
                            {step > 1 && (
                                <Button type="button" variant="outline" startIcon={<ChevronLeft className="size-4" />} onClick={goToPreviousStep}>
                                    Atrás
                                </Button>
                            )}

                            {step < TOTAL_STEPS ? (
                                <Button type="button" className="flex-1" endIcon={<ArrowRight className="size-4" />} onClick={goToNextStep}>
                                    Siguiente
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    className="flex-1"
                                    disabled={isSubmitting}
                                    startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                                    endIcon={isSubmitting ? undefined : <ArrowRight className="size-4" />}
                                >
                                    {isSubmitting ? 'Guardando…' : 'Guardar y continuar'}
                                </Button>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
};
