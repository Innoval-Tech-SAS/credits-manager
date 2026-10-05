import type { FC } from 'react';
import { useEffect, useRef } from 'react';
import { Outlet, useLocation } from 'react-router';

import { PageHeaderProvider } from '@ui/breadcrumb/components/page-header-provider';
import { SidebarProvider } from '@ui/sidebar/components/sidebar-provider';
import { useSidebar } from '@ui/sidebar/hooks/use-sidebar';
import { NotificationsProvider } from '../../notifications/components/notifications-provider';
import { AppHeader } from './app-header';
import { AppPageHeader } from './app-page-header';
import { AppSidebar } from './app-sidebar';

const LayoutContent: FC = () => {
    const { isExpanded, isHovered, isMobileOpen, closeMobileSidebar } = useSidebar();
    const { pathname } = useLocation();
    const scrollerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scrollerRef.current?.scrollTo(0, 0);
    }, [pathname]);

    return (
        <div className="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900 print:h-auto print:overflow-visible print:bg-white">
            {isMobileOpen && (
                <div
                    className="fixed inset-x-0 top-19 bottom-0 z-40 bg-gray-400/50 backdrop-blur-[32px] lg:hidden print:hidden"
                    onClick={closeMobileSidebar}
                    aria-hidden="true"
                />
            )}
            <div className="print:hidden">
                <AppSidebar />
            </div>
            <div
                className={`flex h-screen flex-1 flex-col overflow-hidden transition-all duration-300 ease-in-out print:ml-0! print:h-auto print:overflow-visible ${
                    isExpanded || isHovered ? 'lg:ml-72.5' : 'lg:ml-22.5'
                } ${isMobileOpen ? 'ml-0' : ''}`}
            >
                <div className="print:hidden">
                    <AppHeader />
                </div>
                <div
                    ref={scrollerRef}
                    className={`custom-scrollbar mx-auto w-full ${pathname === '/' ? '' : 'max-w-(--breakpoint-2xl)'} min-h-0 flex-1 overflow-y-auto print:overflow-visible`}
                >
                    {/* Contenedor normal (no flex-1/min-h-0) a propósito: si este wrapper puede
                        quedar más chico que su contenido, el `pb-6` de cada página deja de contar
                        en el scrollHeight real del scroller de arriba y el contenido queda pegado
                        al borde inferior sin margen. Solo el scroller de arriba necesita min-h-0/flex-1
                        (es el que de verdad hace scroll); todo lo de adentro debe abrazar su contenido. */}
                    <div className="p-4 md:p-6 print:p-0">
                        <PageHeaderProvider>
                            <div className="print:hidden">
                                <AppPageHeader />
                            </div>
                            <Outlet />
                        </PageHeaderProvider>
                    </div>
                </div>
            </div>
        </div>
    );
};

export const AppLayout: FC = () => {
    return (
        <NotificationsProvider>
            <SidebarProvider>
                <LayoutContent />
            </SidebarProvider>
        </NotificationsProvider>
    );
};
