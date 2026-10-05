import type { FC } from 'react';

import { Button } from '@ui/button/components/button';
import { Modal } from '@ui/modal/components/modal';
import type { ImportDecisionModalsProps } from '../props/import-decision-modals-props';

/**
 * Las dos decisiones aparecen en orden y no se pueden cerrar sin responder: la segunda espera a que se resuelva la primera.
 * El botón que coincide con la preferencia marcada al subir el archivo va primero y resaltado.
 */
export const ImportDecisionModals: FC<ImportDecisionModalsProps> = ({ importacion, decisiones, isSubmitting, onDecide }) => {
    if (decisiones.requiere_decision_empleadores) {
        const cantidad = importacion.empleadores_pendientes;
        const plural = cantidad !== 1;

        return (
            <Modal isOpen onClose={() => {}} showCloseButton={false} size="md">
                <h4 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">
                    {`Hemos encontrado ${cantidad} empleador${plural ? 'es' : ''} no creado${plural ? 's' : ''}. ¿Quieres crearlo${plural ? 's' : ''}?`}
                </h4>
                <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    Si rechazas, los clientes de esos empleadores quedan rechazados con el motivo en el reporte. Si aceptas, se crean una sola vez y quedan enlazados a sus clientes.
                </p>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant={importacion.preferencia_crear_empleadores ? 'outline' : 'primary'} disabled={isSubmitting} onClick={() => onDecide('crear_empleadores', false)}>
                        Rechazar
                    </Button>
                    <Button variant={importacion.preferencia_crear_empleadores ? 'primary' : 'outline'} disabled={isSubmitting} onClick={() => onDecide('crear_empleadores', true)}>
                        Aceptar
                    </Button>
                </div>
            </Modal>
        );
    }

    if (decisiones.requiere_decision_asiento) {
        return (
            <Modal isOpen onClose={() => {}} showCloseButton={false} size="md">
                <h4 className="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90">Datos validados. ¿Desea que se genere el asiento contable?</h4>
                <p className="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    Si rechazas, los datos se importan sin asiento. Si aceptas, se importan y se genera el asiento contable de la carga.
                </p>
                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button variant={importacion.preferencia_generar_asiento ? 'outline' : 'primary'} disabled={isSubmitting} onClick={() => onDecide('generar_asiento', false)}>
                        Rechazar
                    </Button>
                    <Button variant={importacion.preferencia_generar_asiento ? 'primary' : 'outline'} disabled={isSubmitting} onClick={() => onDecide('generar_asiento', true)}>
                        Aceptar
                    </Button>
                </div>
            </Modal>
        );
    }

    return null;
};
