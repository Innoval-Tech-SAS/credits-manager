import type { ThirdPartyContactValues } from '../types/third-party-contact-values';

export type ThirdPartyContactFieldsProps = {
    values: ThirdPartyContactValues;
    onChange: <K extends keyof ThirdPartyContactValues>(key: K, value: ThirdPartyContactValues[K]) => void;
};
