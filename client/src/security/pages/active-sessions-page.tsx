import type { FC } from 'react';
import { useMemo, useState } from 'react';

import { Button } from '@ui/button/components/button';
import { Select } from '@ui/forms/components/select';
import { ConfirmModal } from '@ui/modal/components/confirm-modal';
import { ModuleListPage } from '@ui/module/components/module-list-page';
import { DataTable } from '@ui/table/components/data-table';
import { useResource } from '../../shared/hooks/use-resource';
import { useSendRequest } from '../../shared/hooks/use-send-request';
import { SessionDetailModal } from '../components/session-detail-modal';
import type { ApiActiveSession } from '../types/api-active-session';
import { buildActiveSessionsTableColumns } from '../utils/build-active-sessions-table-columns';

export const ActiveSessionsPage: FC = () => {
    const { data, error, isLoading, refetch } = useResource<{ sesiones: ApiActiveSession[] }>('/seguridad/sesiones');
    const { sendRequest, isLoading: isClosing } = useSendRequest();

    const [sessionToView, setSessionToView] = useState<ApiActiveSession | null>(null);
    const [sessionToClose, setSessionToClose] = useState<ApiActiveSession | null>(null);
    const [companyToClose, setCompanyToClose] = useState('');
    const [confirmingCompany, setConfirmingCompany] = useState(false);
    const [successMessage, setSuccessMessage] = useState<string | null>(null);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const sessions = data?.sesiones ?? [];

    // Solo las empresas con alguna sesión activa ahora mismo: no hace falta pedir el catálogo completo.
    const companyOptions = useMemo(() => {
        const vistas = new Map<number, string>();

        (data?.sesiones ?? []).forEach((session) => {
            if (session.empresa_id !== null && session.nombre_empresa) {
                vistas.set(session.empresa_id, session.nombre_empresa);
            }
        });

        return Array.from(vistas, ([value, label]) => ({ value: String(value), label }));
    }, [data]);

    const closeSession = async () => {
        if (!sessionToClose) {
            return;
        }

        setErrorMessage(null);

        try {
            await sendRequest(`/seguridad/sesiones/${sessionToClose.id}`, { method: 'DELETE' });
            setSuccessMessage(`Sesión de ${sessionToClose.usuario_nombre} cerrada.`);
            setSessionToClose(null);
            refetch();
        } catch (caught) {
            setErrorMessage(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    const closeCompanySessions = async () => {
        if (!companyToClose) {
            return;
        }

        setErrorMessage(null);

        try {
            const respuesta = await sendRequest<{ message: string }>(`/seguridad/sesiones/empresa/${companyToClose}`, { method: 'DELETE' });
            setSuccessMessage(respuesta.message);
            setConfirmingCompany(false);
            setCompanyToClose('');
            refetch();
        } catch (caught) {
            setErrorMessage(caught instanceof Error ? caught.message : 'Ocurrió un error inesperado.');
        }
    };

    return (
        <ModuleListPage
            pageTitle="Sesiones activas"
            successMessage={successMessage}
            onDismissSuccess={() => setSuccessMessage(null)}
            errorMessage={errorMessage ?? error}
            onDismissError={() => setErrorMessage(null)}
            countLabel={`${sessions.length} ${sessions.length === 1 ? 'sesión activa' : 'sesiones activas'}`}
            toolbar={
                companyOptions.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-full sm:w-52">
                            <Select
                                ariaLabel="Empresa"
                                options={companyOptions}
                                placeholder="Elige una empresa"
                                placeholderSelectable
                                defaultValue={companyToClose}
                                onChange={setCompanyToClose}
                            />
                        </div>
                        <Button variant="outline" disabled={!companyToClose} onClick={() => setConfirmingCompany(true)}>
                            Cerrar todas sus sesiones
                        </Button>
                    </div>
                )
            }
            modals={
                <>
                    <SessionDetailModal session={sessionToView} onClose={() => setSessionToView(null)} />

                    <ConfirmModal
                        isOpen={sessionToClose !== null}
                        onClose={() => setSessionToClose(null)}
                        onConfirm={() => void closeSession()}
                        isConfirming={isClosing}
                        title="¿Cerrar esta sesión?"
                        description={`${sessionToClose?.usuario_nombre ?? 'El usuario'} tendrá que iniciar sesión de nuevo.`}
                        confirmLabel="Cerrar sesión"
                    />

                    <ConfirmModal
                        isOpen={confirmingCompany}
                        onClose={() => setConfirmingCompany(false)}
                        onConfirm={() => void closeCompanySessions()}
                        isConfirming={isClosing}
                        title="¿Cerrar todas las sesiones de esta empresa?"
                        description="Todos sus usuarios con sesión activa tendrán que iniciar sesión de nuevo."
                        confirmLabel="Cerrar todas"
                    />
                </>
            }
        >
            <DataTable
                columns={buildActiveSessionsTableColumns(
                    (session) => setSessionToView(session),
                    (session) => setSessionToClose(session),
                )}
                rows={sessions}
                getRowKey={(session) => session.id}
                loading={isLoading}
                emptyMessage="No hay sesiones activas."
            />
        </ModuleListPage>
    );
};
