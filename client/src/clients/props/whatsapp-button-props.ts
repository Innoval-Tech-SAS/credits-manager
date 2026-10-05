export type WhatsAppButtonProps = {
    nombre: string;
    celular: string | null;
    /** `icono` para tablas y filas; `boton` para la ficha del cliente. */
    variant: 'icono' | 'boton';
};

export type WhatsAppMessageDialogProps = {
    onClose: () => void;
    nombre: string;
    numero: string;
    empresa: string;
};
