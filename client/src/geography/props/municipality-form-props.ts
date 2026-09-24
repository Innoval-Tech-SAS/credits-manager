import type { ApiMunicipality } from '../types/api-municipality';

export type MunicipalityFormProps = {
    onCreated: (municipality: ApiMunicipality) => void;
};
