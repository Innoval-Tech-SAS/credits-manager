import type { InitialMunicipality } from '../types/initial-municipality';

export type MunicipalitySelectorProps = {
    /** Id del municipio elegido, o cadena vacía si aún no hay ninguno. */
    onChange: (municipalityId: string) => void;
    initial?: InitialMunicipality;
    /** Una columna en lugar de tres, para formularios angostos. */
    stacked?: boolean;
};
