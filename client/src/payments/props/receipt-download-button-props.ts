import type { ApiPayment } from '../types/api-payment';

export type ReceiptDownloadButtonProps = {
    payment: Pick<ApiPayment, 'id' | 'recibo'>;
};
