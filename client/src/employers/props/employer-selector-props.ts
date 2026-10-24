import type { ApiEmployer } from '../types/api-employer';

export type EmployerSelectorProps = {
    id?: string;
    selected: ApiEmployer | null;
    onSelect: (employer: ApiEmployer) => void;
};
