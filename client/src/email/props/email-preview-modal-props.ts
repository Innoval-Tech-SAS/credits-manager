import type { ApiEmailPreview } from '../types/api-email-preview';

export type EmailPreviewModalProps = {
    preview: ApiEmailPreview;
    onClose: () => void;
};
