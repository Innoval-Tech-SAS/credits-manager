import type { FC } from 'react';
import { RouterProvider } from 'react-router';

import { AuthProvider } from './auth/components/auth-provider';
import { CompanyProvider } from './company/components/company-provider';
import { router } from './router';
import { ThemeProvider } from '@ui/theme/components/theme-provider';

export const App: FC = () => {
    return (
        <ThemeProvider>
            <AuthProvider>
                <CompanyProvider>
                    <RouterProvider router={router} />
                </CompanyProvider>
            </AuthProvider>
        </ThemeProvider>
    );
};
