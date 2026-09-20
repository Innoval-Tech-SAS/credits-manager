import type { FC } from 'react';

import { Check } from 'lucide-react';
import { ONBOARDING_STEPS } from '../constants/onboarding-steps';
import type { OnboardingStepIndicatorProps } from '../props/onboarding-step-indicator-props';

export const OnboardingStepIndicator: FC<OnboardingStepIndicatorProps> = ({ currentStep }) => {
    return (
        <div className="mb-8 flex items-center">
            {ONBOARDING_STEPS.map((label, index) => {
                const step = index + 1;
                const isCompleted = step < currentStep;
                const isActive = step === currentStep;
                const isLast = index === ONBOARDING_STEPS.length - 1;

                return (
                    <div key={label} className={`flex items-center ${isLast ? '' : 'flex-1'}`}>
                        <div className="flex flex-col items-center gap-2">
                            <div
                                className={`relative flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold ${
                                    isCompleted
                                        ? 'bg-brand-500 text-white'
                                        : isActive
                                          ? 'border-2 border-brand-500 text-brand-500'
                                          : 'border border-gray-300 text-gray-400 dark:border-gray-700'
                                }`}
                            >
                                {isCompleted ? <Check className="size-4" /> : step}
                            </div>
                            <span
                                className={`text-xs whitespace-nowrap ${
                                    isActive ? 'font-medium text-gray-800 dark:text-white/90' : 'text-gray-400'
                                }`}
                            >
                                {label}
                            </span>
                        </div>

                        {!isLast && (
                            <div className={`mx-2 h-px flex-1 ${isCompleted ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'}`} />
                        )}
                    </div>
                );
            })}
        </div>
    );
};
