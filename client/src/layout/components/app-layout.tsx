import type { FC } from 'react';
import { Suspense, useEffect, useRef } from 'react';
import { Outlet, useLocation } from 'react-router';

import { PageHeaderProvider } from '@ui/breadcrumb/components/page-header-provider';
import { SidebarProvider } from '@ui/sidebar/components/sidebar-provider';
import { PageSkeleton } from '@ui/skeleton/components/page-skeleton';
import { useSidebar } from '@ui/sidebar/hooks/use-sidebar';
import { useAuth } from '../../auth/hooks/use-auth';
import { NotificationsProvider } from '../../notifications/components/notifications-provider';
import { AppHeader } from './app-header';
import { AppPageHeader } from './app-page-header';
import { AppSidebar } from './app-sidebar';

const LayoutContent: FC = () => {
    const { isExpanded, isHovered, isMobileOpen, closeMobileSidebar } = useSidebar();
    const { user } = useAuth();
    const { pathname } = useLocation();
    const scrollerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        scrollerRef.current?.scrollTo(0, 0);
    }, [pathname]);

    return (
        <div className="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-900 print:h-auto print:overflow-visible print:bg-white">
            {isMobileOpen && (
                <div
                    className="fixed inset-x-0 top-19 bottom-0 z-40 bg-gray-400/50 backdrop-blur-[32px] lg:hidden dark:bg-gray-900/50 print:hidden"
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
                <PageHeaderProvider>
                    {/* Fuera del scroller: el título, Volver y la ruta de migas quedan fijos y solo hace scroll el contenido. */}
                    <div className="w-full shrink-0 px-4 pt-4 pb-4 md:px-6 md:pt-6 md:pb-6 print:hidden">
                        <AppPageHeader />
                    </div>
                    <div
                        ref={scrollerRef}
                        className="custom-scrollbar w-full min-h-0 flex-1 overflow-y-auto print:overflow-visible"
                    >
                        {/* `min-h-full` (nunca `h-full` ni flex-1/min-h-0): mide al menos el alto visible y
                            crece con su contenido, así el `pb-6` de cada página sigue contando en el
                            scrollHeight del scroller. Es grid porque el área de un ítem de grid estirado es
                            una altura definida: el `min-h-full` de la raíz de cada página (y del esqueleto)
                            se resuelve contra ella y su tarjeta `flex-1` ocupa el alto disponible. */}
                        <div className="grid min-h-full px-4 pb-4 md:px-6 md:pb-6 print:block print:min-h-0 print:p-0">
                            {/* Un único ítem de grid aunque la página devuelva un fragmento; `min-w-0` evita que una tabla ancha lo desborde. */}
                            <div className="min-w-0">
                                {/* `key` por ruta: cada página nueva monta su propio límite y muestra el esqueleto mientras llega su código. */}
                                <Suspense key={pathname} fallback={<PageSkeleton esSuperAdmin={user?.isSuperAdmin ?? false} />}>
                                    <Outlet />
                                </Suspense>
                            </div>
                        </div>
                    </div>
                </PageHeaderProvider>
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
