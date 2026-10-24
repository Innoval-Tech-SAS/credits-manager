import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import type { ClientInfoCardProps } from '../props/client-info-card-props';
import { Pencil } from 'lucide-react';

export const ClientInfoCard: FC<ClientInfoCardProps> = ({ cliente, canEdit, onEdit }) => (
    <div className="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
        <div className="mb-4 flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-800 dark:text-white/90">Datos del cliente</h2>
            {canEdit && (
                <Button iconOnly startIcon={<Pencil className="size-4" />} variant="outline" onClick={onEdit}>
                    Editar
                </Button>
            )}
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Dirección</p>
                <p className="font-medium text-gray-800 dark:text-white/90">
                    {cliente.tercero.direccion ?? '—'}
                    {cliente.tercero.barrio && `, ${cliente.tercero.barrio}`}
                </p>
            </div>
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Ciudad</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.tercero.ciudad ?? '—'}</p>
            </div>
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Celular</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.tercero.celular ?? '—'}</p>
            </div>
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Teléfono fijo</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.tercero.telefono_fijo ?? '—'}</p>
            </div>
            <div className="wrap-break-word">
                <p className="text-sm text-gray-500 dark:text-gray-400">Correo</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.tercero.correo ?? '—'}</p>
            </div>
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Empresa</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.empleador?.nombre ?? '—'}</p>
            </div>
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">Asesor</p>
                <p className="font-medium text-gray-800 dark:text-white/90">{cliente.asesor?.nombre ?? '—'}</p>
            </div>
        </div>
    </div>
);
