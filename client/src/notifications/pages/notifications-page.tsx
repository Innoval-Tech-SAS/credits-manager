import type { FC } from 'react';
import { useState } from 'react';
import { useNavigate } from 'react-router';

import { Bell } from 'lucide-react';
import { usePaginatedResource } from '../../shared/hooks/use-paginated-resource';
import { useDownloadExport } from '../../shared/hooks/use-download-export';
import { Select } from '@ui/forms/components/select';
import { Button } from '@ui/button/components/button';
import { Spinner } from '@ui/spinner/components/spinner';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { Pagination } from '@ui/table/components/pagination';
import type { BreadcrumbItem } from '@ui/breadcrumb/types/breadcrumb-item';
import { NotificationDetailModal } from '../components/notification-detail-modal';
import { NotificationList } from '../components/notification-list';
import { useNotifications } from '../hooks/use-notifications';
import { buildNotificationLink } from '../utils/build-notification-link';
import { NOTIFICATION_TYPE_LABELS } from '../constants/notification-type-labels';
import type { ApiNotification } from '../types/api-notification';

const TYPE_OPTIONS = Object.entries(NOTIFICATION_TYPE_LABELS).map(([value, label]) => ({ value, label }));

const STATUS_OPTIONS = [
    { value: 'no_leidas', label: 'No leídas' },
    { value: 'leidas', label: 'Leídas' },
];

/** Esta página no tiene entrada en el sidebar (se llega por "Ver todas" desde la campana), así que la ruta de migas no sale del árbol de navegación. */
const BREADCRUMB_TRAIL: BreadcrumbItem[] = [{ name: 'Mis Notificaciones', icon: Bell }];

export const NotificationsPage: FC = () => {
    const navigate = useNavigate();
    const { markAsRead, markAllAsRead, unreadCount } = useNotifications();
    const { download, isDownloading } = useDownloadExport();

    const [tipo, setTipo] = useState('');
    const [leida, setLeida] = useState('');
    const [detailNotification, setDetailNotification] = useState<ApiNotification | null>(null);

    const { items, isLoading, page, setPage, totalPages, totalItems, refetch } = usePaginatedResource<ApiNotification>('/notificaciones/pagina', {
        tipo,
        leida,
    });

    const handleSelect = (notification: ApiNotification) => {
        if (!notification.leida_en) {
            void markAsRead(notification.id)
                .catch(() => {})
                .finally(refetch);
        }

        const destino = buildNotificationLink(notification);

        if (destino) {
            navigate(destino);
        } else {
            setDetailNotification(notification);
        }
    };

    const handleDownload = (notification: ApiNotification) => {
        const exportId = notification.datos?.exportacion_id;

        if (exportId === undefined) {
            return;
        }

        void download(exportId).then(() => {
            if (!notification.leida_en) {
                void markAsRead(notification.id)
                    .catch(() => {})
                    .finally(refetch);
            }
        });
    };

    return (
        <ModuleListPage
            pageTitle="Notificaciones"
            breadcrumbTrail={BREADCRUMB_TRAIL}
            countLabel={totalItems === 1 ? '1 notificación' : `${totalItems} notificaciones`}
            toolbar={
                <>
                    <Select
                        id="filtro-tipo-notificacion"
                        ariaLabel="Tipo de notificación"
                        options={TYPE_OPTIONS}
                        placeholder="Todos los tipos"
                        placeholderSelectable
                        defaultValue={tipo}
                        onChange={(value) => {
                            setTipo(value);
                            setPage(1);
                        }}
                        className="w-full sm:w-56"
                    />
                    <Select
                        id="filtro-estado-notificacion"
                        ariaLabel="Estado de lectura"
                        options={STATUS_OPTIONS}
                        placeholder="Todas"
                        placeholderSelectable
                        defaultValue={leida}
                        onChange={(value) => {
                            setLeida(value);
                            setPage(1);
                        }}
                        className="w-full sm:w-40"
                    />
                    {unreadCount > 0 && (
                        <Button variant="outline" onClick={() => void markAllAsRead().finally(refetch)}>
                            Marcar todas como leídas
                        </Button>
                    )}
                </>
            }
            pagination={<Pagination page={page} totalPages={totalPages} onPageChange={setPage} />}
            modals={<NotificationDetailModal notification={detailNotification} onClose={() => setDetailNotification(null)} />}
        >
            {isLoading && items.length === 0 ? (
                <div className="flex items-center justify-center gap-2 py-10 text-sm text-gray-400 dark:text-gray-500">
                    <Spinner size="sm" />
                    Cargando…
                </div>
            ) : (
                <NotificationList
                    notifications={items}
                    emptyMessage="No tienes notificaciones."
                    onSelect={handleSelect}
                    onDownload={handleDownload}
                    isDownloading={isDownloading}
                />
            )}
        </ModuleListPage>
    );
};
