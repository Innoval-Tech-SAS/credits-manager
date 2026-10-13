import type { FC } from 'react';
import { useState } from 'react';

import { PageHeaderContext } from '../hooks/use-page-header';
import type { PageHeaderContextValue } from '../types/page-header-context-value';
import type { WithChildrenProps } from '../../../shared/props/with-children-props';

export const PageHeaderProvider: FC<WithChildrenProps> = ({ children }) => {
    const [title, setTitle] = useState('');
    const [trail, setTrail] = useState<PageHeaderContextValue['trail']>(null);

    return <PageHeaderContext.Provider value={{ title, setTitle, trail, setTrail }}>{children}</PageHeaderContext.Provider>;
};
