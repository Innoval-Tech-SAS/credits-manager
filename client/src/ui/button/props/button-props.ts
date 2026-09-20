import type { MouseEventHandler, ReactNode } from 'react';

export type ButtonProps = {
    children: ReactNode;
    type?: 'button' | 'submit' | 'reset';
    size?: 'sm' | 'md';
    variant?: 'primary' | 'outline';
    startIcon?: ReactNode;
    endIcon?: ReactNode;
    onClick?: MouseEventHandler<HTMLButtonElement>;
    disabled?: boolean;
    className?: string;
};
