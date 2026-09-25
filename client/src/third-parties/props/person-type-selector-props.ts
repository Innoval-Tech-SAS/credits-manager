import type { PersonType } from '../types/person-type';

export type PersonTypeSelectorProps = {
    value: PersonType | null;
    onChange: (value: PersonType) => void;
};
