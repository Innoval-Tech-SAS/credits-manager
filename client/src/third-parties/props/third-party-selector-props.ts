import type { ApiThirdParty } from '../types/api-third-party';

export type ThirdPartySelectorProps = {
    id?: string;
    selected: ApiThirdParty | null;
    onSelect: (thirdParty: ApiThirdParty) => void;
};
