import type { FC } from 'react';
import { useCallback, useEffect, useState } from 'react';

import { useAuth } from '../../auth/hooks/use-auth';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { CompanyContext } from '../hooks/use-company';
import { mapCompany } from '../utils/map-company';
import type { ApiCompany } from '../types/api-company';
import type { Company } from '../types/company';
import type { WithChildrenProps } from '../../shared/props/with-children-props';

export const CompanyProvider: FC<WithChildrenProps> = ({ children }) => {
    const { user, isLoading: isAuthLoading } = useAuth();
    const [company, setCompany] = useState<Company | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const { sendRequest } = useSendRequest();

    const refresh = useCallback(async () => {
        const data = await sendRequest<{ empresa: ApiCompany | null }>('/empresa');
        setCompany(data.empresa ? mapCompany(data.empresa) : null);
    }, [sendRequest]);

    useEffect(() => {
        if (isAuthLoading || !user) {
            return;
        }

        sendRequest<{ empresa: ApiCompany | null }>('/empresa')
            .then((data) => setCompany(data.empresa ? mapCompany(data.empresa) : null))
            .catch(() => setCompany(null))
            .finally(() => setIsLoading(false));
    }, [user, isAuthLoading, sendRequest]);

    const effectiveCompany = user ? company : null;
    const effectiveIsLoading = isAuthLoading || (user ? isLoading : false);

    return (
        <CompanyContext.Provider value={{ company: effectiveCompany, isLoading: effectiveIsLoading, refresh }}>
            {children}
        </CompanyContext.Provider>
    );
};
