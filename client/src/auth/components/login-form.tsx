import type { FC, SubmitEvent } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { Button } from '@ui/button/components/button';
import { Input } from '@ui/forms/components/input';
import { Label } from '@ui/forms/components/label';
import { Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../hooks/use-auth';

export const LoginForm: FC = () => {
    const { login } = useAuth();
    const navigate = useNavigate();

    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError(null);
        setIsSubmitting(true);

        try {
            await login(username, password);
            navigate('/', { replace: true });
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="space-y-6">
                {error && (
                    <div className="rounded-lg border border-error-500 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-800 dark:bg-error-500/10 dark:text-error-400">
                        {error}
                    </div>
                )}

                <div>
                    <Label htmlFor="username">Usuario</Label>
                    <Input
                        id="username"
                        name="username"
                        autoComplete="username"
                        value={username}
                        onChange={(event) => setUsername(event.target.value)}
                    />
                </div>

                <div>
                    <Label htmlFor="password">Contraseña</Label>
                    <Input
                        id="password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        autoComplete="current-password"
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
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                    />
                </div>

                <Button type="submit" className="w-full" disabled={isSubmitting}>
                    {isSubmitting ? 'Ingresando…' : 'Ingresar'}
                </Button>
            </div>
        </form>
    );
};
