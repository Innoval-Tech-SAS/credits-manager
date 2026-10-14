import type { ApiCompany } from './api-company';

export type BuildCompanyTableColumnsOptions = {
    onVerDashboard: (row: ApiCompany) => void;
};
