import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';

import { useSendRequest } from '../../shared/hooks/use-send-request';
import { Alert } from '@ui/alert/components/alert';
import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Eye, EyeOff, KeyRound } from 'lucide-react';
import { Modal } from '@ui/modal/components/modal';
import { Spinner } from '@ui/spinner/components/spinner';
import type { ChangeOwnPasswordModalProps } from '../props/change-own-password-modal-props';

export const ChangeOwnPasswordModal: FC<ChangeOwnPasswordModalProps> = ({ onClose }) => {
    const { sendRequest } = useSendRequest();

    const [currentPassword, setCurrentPassword] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);

        if (newPassword !== confirmPassword) {
            setError('Las contraseñas no coinciden.');
            return;
        }

        setIsSubmitting(true);

        try {
            await sendRequest('/me/clave', {
                method: 'PATCH',
                body: { clave_actual: currentPassword, clave_nueva: newPassword },
            });
            setSuccess(true);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal isOpen onClose={onClose} size="sm">
            <h4 className="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">Cambiar mi contraseña</h4>

            {success ? (
                <div className="space-y-6">
                    <Alert variant="success" title="Listo" message="Tu contraseña se actualizó correctamente." />
                    <div className="flex justify-end">
                        <Button onClick={onClose}>Cerrar</Button>
                    </div>
                </div>
            ) : (
                <form onSubmit={handleSubmit}>
                    <div className="space-y-5">
                        {error && (
                            <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                                {error}
                            </div>
                        )}

                        <div>
                            <Label htmlFor="current-password">Contraseña actual</Label>
                            <Input
                                id="current-password"
                                name="current-password"
                                type={showPassword ? 'text' : 'password'}
                                startIcon={<KeyRound className="size-5" />}
                                endIcon={
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="cursor-pointer"
                                    >
                                        {showPassword ? (
                                            <Eye className="fill-gray-500 dark:fill-gray-400 size-5" />
                                        ) : (
                                            <EyeOff className="fill-gray-500 dark:fill-gray-400 size-5" />
                                        )}
                                    </button>
                                }
                                value={currentPassword}
                                onChange={(event) => setCurrentPassword(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="new-password">Nueva contraseña</Label>
                            <Input
                                id="new-password"
                                name="new-password"
                                type={showPassword ? 'text' : 'password'}
                                startIcon={<KeyRound className="size-5" />}
                                minLength={8}
                                hint="Mínimo 8 caracteres, con letras, números y un carácter especial."
                                value={newPassword}
                                onChange={(event) => setNewPassword(event.target.value)}
                            />
                        </div>

                        <div>
                            <Label htmlFor="confirm-new-password">Confirmar contraseña nueva</Label>
                            <Input
                                id="confirm-new-password"
                                name="confirm-new-password"
                                type={showPassword ? 'text' : 'password'}
                                startIcon={<KeyRound className="size-5" />}
                                minLength={8}
                                value={confirmPassword}
                                onChange={(event) => setConfirmPassword(event.target.value)}
                            />
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <Button variant="outline" type="button" onClick={onClose} disabled={isSubmitting}>
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSubmitting}
                                startIcon={isSubmitting ? <Spinner size="sm" color="white" /> : undefined}
                            >
                                {isSubmitting ? 'Guardando…' : 'Guardar'}
                            </Button>
                        </div>
                    </div>
                </form>
            )}
        </Modal>
    );
};
