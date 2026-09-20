import type { Theme } from '../types/theme';

export function readStoredTheme(): Theme {
    return (localStorage.getItem('theme') as Theme | null) ?? 'light';
}
