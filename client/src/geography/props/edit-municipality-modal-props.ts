import type { ApiMunicipality } from '../types/api-municipality';

export type EditMunicipalityModalProps = {
    municipality: ApiMunicipality;
    onClose: () => void;
    onSaved: () => void;
};
