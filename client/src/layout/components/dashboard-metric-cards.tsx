import type { FC, ReactNode } from 'react';
import { Coins, CreditCard, HandCoins, Receipt, CircleCheck, TriangleAlert, Users } from 'lucide-react';

import { formatMoney } from '../../shared/utils/format-money';
import { DashboardMoraCard } from './dashboard-mora-card';
import { StatCard } from './stat-card';
import type { DashboardMetricCardsProps } from '../props/dashboard-metric-cards-props';
import { buildCambioStatCard } from '../utils/build-cambio-stat-card';

/** En `2xl` cada tarjeta tiene una columna propia, para que no se estire más de lo que necesita su contenido. */
const COLUMNAS_2XL: Record<number, string> = {
    2: '2xl:grid-cols-2',
    3: '2xl:grid-cols-3',
    4: '2xl:grid-cols-4',
    5: '2xl:grid-cols-5',
    6: '2xl:grid-cols-6',
    7: '2xl:grid-cols-7',
    8: '2xl:grid-cols-8',
};

/**
 * Columnas en `xl` según el número de tarjetas visibles, para que ninguna fila quede a medias ni con tarjetas
 * estiradas. Las clases van completas para que Tailwind las genere.
 */
function columnasXl(total: number): string {
    if (total === 6) {
        return 'xl:grid-cols-3';
    }

    if (total % 4 === 0) {
        return 'xl:grid-cols-4';
    }

    if (total % 3 === 0) {
        return 'xl:grid-cols-3';
    }

    if (total % 2 === 0) {
        return 'xl:grid-cols-2';
    }

    if (total === 5) {
        return 'xl:grid-cols-5';
    }

    return 'xl:grid-cols-4';
}

/**
 * Con 2 columnas en `sm`, la última tarjeta impar ocupa el ancho entero; con 4 en `xl`, 7 tarjetas dejan una última
 * de 2 columnas. Desde `2xl` todas tienen una columna y el span se deshace.
 */
function spanParaTarjeta(indice: number, total: number): string {
    const ultima = indice === total - 1;
    const clases: string[] = [];

    if (total % 2 === 1 && ultima) {
        clases.push('sm:col-span-2 2xl:col-span-1');
    }

    if (total % 4 === 3 && ultima) {
        clases.push('xl:col-span-2 2xl:col-span-1');
    }

    return clases.join(' ');
}

/** Las tarjetas de cada módulo solo aparecen si el usuario tiene su permiso. */
export const DashboardMetricCards: FC<DashboardMetricCardsProps> = ({ metricas }) => {
    const { colocacion, recaudo, gastos, cartera } = metricas;

    const tarjetas: ReactNode[] = [];

    if (colocacion) {
        tarjetas.push(<StatCard key="colocacion" label="Colocación del mes" value={formatMoney(colocacion.valor)} icon={HandCoins} cambio={buildCambioStatCard(colocacion)} />);
    }

    if (recaudo) {
        tarjetas.push(<StatCard key="recaudo" label="Recaudo del mes" value={formatMoney(recaudo.valor)} icon={Coins} cambio={buildCambioStatCard(recaudo)} />);
    }

    if (gastos) {
        tarjetas.push(<StatCard key="gastos" label="Gastos del mes" value={formatMoney(gastos.valor)} icon={Receipt} cambio={buildCambioStatCard(gastos, true)} />);
    }

    tarjetas.push(<StatCard key="clientes" label="Clientes activos" value={String(metricas.clientes_activos)} icon={Users} />);
    tarjetas.push(<StatCard key="creditos" label="Créditos activos" value={String(metricas.creditos_activos)} icon={CreditCard} />);

    if (cartera) {
        tarjetas.push(<StatCard key="al-dia" label="Cartera al día" value={formatMoney(cartera.al_dia)} icon={CircleCheck} accent="success" />);
        tarjetas.push(<StatCard key="vencida" label="Cartera vencida" value={formatMoney(cartera.vencida)} icon={TriangleAlert} accent="error" />);
    }

    return (
        <>
            <div className={`mb-6 grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6 ${columnasXl(tarjetas.length)} ${COLUMNAS_2XL[tarjetas.length] ?? ''}`}>
                {tarjetas.map((tarjeta, indice) => (
                    <div key={indice} className={`h-full ${spanParaTarjeta(indice, tarjetas.length)}`}>
                        {tarjeta}
                    </div>
                ))}
            </div>

            {cartera && <DashboardMoraCard mora={cartera.mora} />}
        </>
    );
};
