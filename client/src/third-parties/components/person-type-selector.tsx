import type { FC } from 'react';
import { Building2, User } from 'lucide-react';

import { PERSON_TYPE_DESCRIPTIONS, PERSON_TYPE_LABELS } from '../constants/person-type-choices';
import type { PersonTypeSelectorProps } from '../props/person-type-selector-props';
import type { PersonType } from '../types/person-type';

const PERSON_TYPES: PersonType[] = ['natural', 'juridica'];

const PERSON_TYPE_ICONS: Record<PersonType, FC<{ className?: string }>> = {
    natural: User,
    juridica: Building2,
};

export const PersonTypeSelector: FC<PersonTypeSelectorProps> = ({ value, onChange }) => {
    return (
        <div>
            <p className="mb-3 text-xs font-medium tracking-wide text-gray-400 uppercase dark:text-gray-500">Tipo de persona</p>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {PERSON_TYPES.map((personType) => {
                    const Icon = PERSON_TYPE_ICONS[personType];
                    const isSelected = value === personType;

                    return (
                        <button
                            key={personType}
                            type="button"
                            aria-pressed={isSelected}
                            onClick={() => onChange(personType)}
                            className={`flex items-start gap-4 rounded-2xl border p-5 text-left transition-colors ${
                                isSelected
                                    ? 'border-brand-500 bg-brand-50 dark:border-brand-500 dark:bg-brand-500/10'
                                    : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-800 dark:bg-white/3 dark:hover:border-gray-700'
                            }`}
                        >
                            <span
                                className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${
                                    isSelected
                                        ? 'bg-brand-500 text-white'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                                }`}
                            >
                                <Icon className="size-5" />
                            </span>
                            <span>
                                <span className="block text-sm font-medium text-gray-800 dark:text-white/90">{PERSON_TYPE_LABELS[personType]}</span>
                                <span className="mt-1 block text-theme-xs text-gray-500 dark:text-gray-400">{PERSON_TYPE_DESCRIPTIONS[personType]}</span>
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
};
