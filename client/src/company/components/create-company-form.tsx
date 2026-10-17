import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Spinner } from '@ui/spinner/components/spinner';
import { calculateVerificationDigit } from '../../shared/utils/calculate-verification-digit';
import { MunicipalitySelector } from '../../geography/components/municipality-selector';
import { Building2, Hash, IdCard, KeyRound, Mail, MapPin, Phone, User, Save } from 'lucide-react';

export const CreateCompanyForm: FC = () => {
    const navigate = useNavigate();
    const { sendRequest } = useSendRequest();

    const [name, setName] = useState('');
    const [taxId, setTaxId] = useState('');
    const taxVerificationDigit = calculateVerificationDigit(taxId);
    const [address, setAddress] = useState('');
    const [municipalityId, setMunicipalityId] = useState('');
    const [economicActivityCode, setEconomicActivityCode] = useState('');
    const [economicActivityName, setEconomicActivityName] = useState('');
    const [phone, setPhone] = useState('');
    const [email, setEmail] = useState('');

    const [adminName, setAdminName] = useState('');
    const [adminUsername, setAdminUsername] = useState('');
    const [adminPassword, setAdminPassword] = useState('');
    const [adminConfirmPassword, setAdminConfirmPassword] = useState('');

    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        if (adminPassword !== adminConfirmPassword) {
            setError('Las contraseñas del administrador no coinciden.');
            return;
        }

        if (Boolean(economicActivityCode) !== Boolean(economicActivityName)) {
            setError('El código CIIU y el nombre de la actividad económica deben completarse juntos.');
            return;
        }

        setIsSubmitting(true);

        try {
            await sendRequest('/empresas', {
                method: 'POST',
                body: {
                    nombre_empresa: name,
                    nit: taxId,
                    digito_verificacion: taxVerificationDigit,
                    direccion: address,
                    municipio_id: Number(municipalityId),
                    actividad_economica_codigo: economicActivityCode || null,
                    actividad_economica_nombre: economicActivityName || null,
                    telefono: phone,
                    correo: email,
                    admin_nombre: adminName,
                    admin_usuario: adminUsername,
                    admin_password: adminPassword,
                },
            });

            navigate('/empresas', { replace: true, state: { message: 'Empresa creada correctamente.' } });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-8">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div className="flex flex-col gap-8 xl:grid xl:grid-cols-2 xl:items-start xl:gap-x-12">
                    <section>
                        <h3 className="mb-4 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Datos de la empresa
                        </h3>
                        <div className="space-y-6">
                            <div>
                                <Label htmlFor="name">Nombre de la empresa</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    autoComplete="organization"
                                    required
                                    startIcon={<Building2 className="size-4" />}
                                    value={name}
                                    onChange={(event) => setName(event.target.value)}
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="tax-id">NIT</Label>
                                    <Input
                                        id="tax-id"
                                        name="tax-id"
                                        required
                                        minLength={6}
                                        maxLength={15}
                                        startIcon={<IdCard className="size-4" />}
                                        value={taxId}
                                        onChange={(event) => setTaxId(event.target.value.replace(/\D/g, ''))}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="tax-verification-digit">Dígito de verificación</Label>
                                    <Input
                                        id="tax-verification-digit"
                                        name="tax-verification-digit"
                                        disabled
                                        value={taxVerificationDigit}
                                        hint="Se calcula a partir del NIT."
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="address">Dirección</Label>
                                <Input
                                    id="address"
                                    name="address"
                                    autoComplete="street-address"
                                    required
                                    startIcon={<MapPin className="size-4" />}
                                    value={address}
                                    onChange={(event) => setAddress(event.target.value)}
                                />
                            </div>

                            <MunicipalitySelector stacked onChange={setMunicipalityId} />

                            <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="economic-activity-code">Código CIIU</Label>
                                    <Input
                                        id="economic-activity-code"
                                        name="economic-activity-code"
                                        startIcon={<Hash className="size-4" />}
                                        value={economicActivityCode}
                                        onChange={(event) => setEconomicActivityCode(event.target.value)}
                                    />
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="economic-activity-name">Actividad económica</Label>
                                    <Input
                                        id="economic-activity-name"
                                        name="economic-activity-name"
                                        value={economicActivityName}
                                        onChange={(event) => setEconomicActivityName(event.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="phone">Teléfono</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        autoComplete="tel"
                                        required
                                        startIcon={<Phone className="size-4" />}
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
                                        autoComplete="email"
                                        required
                                        startIcon={<Mail className="size-4" />}
                                        value={email}
                                        onChange={(event) => setEmail(event.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="border-t border-gray-100 pt-8 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-12 dark:border-gray-800">
                        <h3 className="mb-4 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            Primer usuario administrador
                        </h3>
                        <div className="space-y-6">
                            <div>
                                <Label htmlFor="admin-name">Nombre</Label>
                                <Input
                                    id="admin-name"
                                    name="admin-name"
                                    autoComplete="name"
                                    required
                                    startIcon={<User className="size-4" />}
                                    value={adminName}
                                    onChange={(event) => setAdminName(event.target.value)}
                                />
                            </div>

                            <div>
                                <Label htmlFor="admin-username">Usuario</Label>
                                <Input
                                    id="admin-username"
                                    name="admin-username"
                                    autoComplete="off"
                                    required
                                    startIcon={<User className="size-4" />}
                                    value={adminUsername}
                                    onChange={(event) => setAdminUsername(event.target.value)}
                                />
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="admin-password">Contraseña</Label>
                                    <Input
                                        id="admin-password"
                                        name="admin-password"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                        startIcon={<KeyRound className="size-4" />}
                                        minLength={8}
                                        hint="Mínimo 8 caracteres, con letras, números y símbolos."
                                        value={adminPassword}
                                        onChange={(event) => setAdminPassword(event.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="admin-confirm-password">Confirmar contraseña</Label>
                                    <Input
                                        id="admin-confirm-password"
                                        name="admin-confirm-password"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                        startIcon={<KeyRound className="size-4" />}
                                        minLength={8}
                                        value={adminConfirmPassword}
                                        onChange={(event) => setAdminConfirmPassword(event.target.value)}
                                    />
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div className="flex justify-end border-t border-gray-100 pt-6 dark:border-gray-800">
                    <Button
                        type="submit"
                        disabled={isSubmitting || !municipalityId}
                        startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                        endIcon={isSubmitting ? undefined : <Save className="size-4" />}
                    >
                        {isSubmitting ? 'Creando…' : 'Crear empresa'}
                    </Button>
                </div>
            </div>
        </form>
    );
};
