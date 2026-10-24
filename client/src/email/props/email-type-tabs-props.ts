import type { EmailType } from '../types/email-type';

export type EmailTypeTabsProps = {
    tipo: EmailType;
    onChange: (tipo: EmailType) => void;
};
