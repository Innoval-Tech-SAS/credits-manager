import type { EmailType } from '../types/email-type';

export type EmailTestSendModalProps = {
    tipo: EmailType;
    onClose: () => void;
};
