import type { FC } from 'react';
import { Check } from 'lucide-react';

import type { ImportStepsProps } from '../props/import-steps-props';

export const ImportSteps: FC<ImportStepsProps> = ({ steps, currentStep }) => (
    <ol className="flex flex-wrap gap-2" aria-label="Pasos de la importación">
        {steps.map((step, index) => {
            const isDone = index < currentStep;
            const isActive = index === currentStep;

            return (
                <li
                    key={step}
                    aria-current={isActive ? 'step' : undefined}
                    className={`flex items-center gap-2 rounded-full border px-3 py-1.5 text-theme-xs font-medium ${
                        isActive
                            ? 'border-brand-500 bg-brand-50 text-brand-600 dark:border-brand-500 dark:bg-brand-500/10 dark:text-brand-400'
                            : isDone
                              ? 'border-success-200 text-success-600 dark:border-success-800 dark:text-success-400'
                              : 'border-gray-200 text-gray-500 dark:border-gray-800 dark:text-gray-400'
                    }`}
                >
                    <span className="flex size-5 items-center justify-center rounded-full bg-current/10">
                        {isDone ? <Check className="size-3" /> : index + 1}
                    </span>
                    {step}
                </li>
            );
        })}
    </ol>
);
