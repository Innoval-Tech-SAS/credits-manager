import type { FC } from 'react';
import { useState } from 'react';

import { PageHeaderContext } from '../hooks/use-page-header';
import type { WithChildrenProps } from '../../../shared/props/with-children-props';

export const PageHeaderProvider: FC<WithChildrenProps> = ({ children }) => {
    const [title, setTitle] = useState('');

    return <PageHeaderContext.Provider value={{ title, setTitle }}>{children}</PageHeaderContext.Provider>;
};
