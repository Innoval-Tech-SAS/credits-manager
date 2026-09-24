import type { FC } from 'react';
import { useEffect, useState } from 'react';

import { SidebarContext } from '../hooks/use-sidebar';
import type { WithChildrenProps } from '../../../shared/props/with-children-props';

export const SidebarProvider: FC<WithChildrenProps> = ({ children }) => {
    const [isExpanded, setIsExpanded] = useState(false);
    const [isMobileOpen, setIsMobileOpen] = useState(false);
    const [isMobile, setIsMobile] = useState(false);
    const [isHovered, setIsHovered] = useState(false);

    useEffect(() => {
        const handleResize = () => {
            const mobile = window.innerWidth < 768;
            setIsMobile(mobile);
            if (!mobile) {
                setIsMobileOpen(false);
            }
        };

        handleResize();
        window.addEventListener('resize', handleResize);

        return () => {
            window.removeEventListener('resize', handleResize);
        };
    }, []);

    const toggleSidebar = () => {
        setIsExpanded((previous) => !previous);
    };

    const toggleMobileSidebar = () => {
        setIsMobileOpen((previous) => !previous);
    };

    const closeMobileSidebar = () => {
        setIsMobileOpen(false);
    };

    return (
        <SidebarContext.Provider
            value={{
                isExpanded: isMobile ? false : isExpanded,
                isMobileOpen,
                isHovered,
                toggleSidebar,
                toggleMobileSidebar,
                closeMobileSidebar,
                setIsHovered,
            }}
        >
            {children}
        </SidebarContext.Provider>
    );
};
