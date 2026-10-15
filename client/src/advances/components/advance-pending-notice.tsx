import type { FC } from 'react';
import { Link } from 'react-router';
import { TriangleAlert } from 'lucide-react';

import { useResource } from '../../shared/hooks/use-resource';
import { formatMoney } from '../../shared/utils/format-money';
import type { ApiCarteraDocument } from '../../accounting/types/api-cartera-document';
import type { AdvancePendingNoticeProps } from '../props/advance-pending-notice-props';

/** Anticipos de proveedor pendientes del tercero elegido: Gastos y Causación siempre generan un documento por pagar, así que el anticipo relevante es siempre `anticipo_proveedor`. El cruce en sí se hace en la pantalla dedicada, no aquí. */
export const AdvancePendingNotice: FC<AdvancePendingNoticeProps> = ({ tercero }) => {
    const { data } = useResource<{ data: ApiCarteraDocument[] }>(
        tercero ? `/cartera-terceros?naturaleza=anticipo_proveedor&tercero_id=${tercero.id}&estado=pendiente&per_page=100` : null,
    );
    const anticipos = data?.data ?? [];

    if (!tercero || anticipos.length === 0) {
        return null;
    }

    const saldoTotal = anticipos.reduce((total, anticipo) => total + Number(anticipo.saldo), 0);

    return (
        <div className="flex items-start gap-3 rounded-xl bg-warning-50 p-4 dark:bg-warning-500/15">
            <span className="mt-0.5 text-warning-600 dark:text-orange-400">
                <TriangleAlert className="size-5" />
            </span>

            <div className="flex-1">
                <p className="text-sm text-gray-700 dark:text-gray-300">
                    {tercero.nombre} tiene {anticipos.length === 1 ? 'un anticipo pendiente' : `${anticipos.length} anticipos pendientes`} por{' '}
                    {formatMoney(saldoTotal)}.
                </p>
                <Link
                    to="/contabilidad/anticipos/cruzar"
                    state={{ terceroPreseleccionado: tercero }}
                    className="mt-1 inline-block text-sm font-medium text-warning-600 hover:underline dark:text-orange-400"
                >
                    Ir a cruzar anticipo →
                </Link>
            </div>
        </div>
    );
};
