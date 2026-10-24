import type { ReactNode } from 'react';

export type BackupDownloadCardProps = {
    title: string;
    description: string;
    buttonLabel: string;
    icon: ReactNode;
    endpoint: string;
    fallbackFilename: string;
};
