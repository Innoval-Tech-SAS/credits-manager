import type { ApiMunicipality } from '../types/api-municipality';

export type MunicipalityFormProps = {
    onCreated: (municipio: ApiMunicipality) => void;
};
