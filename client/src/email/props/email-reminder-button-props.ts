import type { ApiCollectibleInstallment } from '../types/api-email-dispatch';

export type EmailReminderButtonProps = {
    clienteId: number;
    correo: string | null;
    cuotasCobrables: ApiCollectibleInstallment[];
    onSent: () => void;
};

export type EmailReminderModalProps = {
    clienteId: number;
    cuotasCobrables: ApiCollectibleInstallment[];
    correo: string;
    onClose: () => void;
    onSent: () => void;
};
