import type { FC, FocusEvent, MouseEvent, TouchEvent } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import type { TooltipProps } from '../props/tooltip-props';

const LONG_PRESS_MS = 450;
const TOUCH_VISIBLE_MS = 1800;
const MD_QUERY = '(min-width: 768px)';
const EDGE = 8;

type Anchor = { top: number; bottom: number; center: number };

/**
 * Muestra `label` al pasar el ratón, al enfocar con el teclado y al mantener el dedo presionado.
 * Se dibuja en un portal fijo al `body` para que no lo recorte ningún contenedor con scroll (tablas, modales).
 * Envuelve al hijo en un `span`: el hijo debe llevar su propio `aria-label` para lectores de pantalla.
 */
export const Tooltip: FC<TooltipProps> = ({ label, hideFrom, children }) => {
    const [anchor, setAnchor] = useState<Anchor | null>(null);
    const pressTimer = useRef<number | undefined>(undefined);
    const hideTimer = useRef<number | undefined>(undefined);
    const tooltipRef = useRef<HTMLDivElement>(null);

    useEffect(
        () => () => {
            window.clearTimeout(pressTimer.current);
            window.clearTimeout(hideTimer.current);
        },
        [],
    );

    // El tooltip es fijo: si la página se desplaza o cambia de tamaño mientras está abierto, se cierra en vez de quedar suelto.
    useEffect(() => {
        if (!anchor) {
            return;
        }

        const close = () => setAnchor(null);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);

        return () => {
            window.removeEventListener('scroll', close, true);
            window.removeEventListener('resize', close);
        };
    }, [anchor]);

    const show = (element: HTMLElement) => {
        if (hideFrom && window.matchMedia(MD_QUERY).matches) {
            return;
        }

        const rect = element.getBoundingClientRect();
        setAnchor({ top: rect.top, bottom: rect.bottom, center: rect.left + rect.width / 2 });
    };

    const hide = () => {
        window.clearTimeout(pressTimer.current);
        window.clearTimeout(hideTimer.current);
        setAnchor(null);
    };

    // Se centra sobre el elemento y solo se corre lo justo para no salirse de la pantalla, según su ancho real.
    useLayoutEffect(() => {
        if (!anchor || !tooltipRef.current) {
            return;
        }

        const half = tooltipRef.current.offsetWidth / 2;
        tooltipRef.current.style.left = `${Math.min(Math.max(anchor.center, half + EDGE), window.innerWidth - half - EDGE)}px`;
    }, [anchor]);

    const above = anchor ? anchor.top > 48 : true;

    return (
        <>
            <span
                className="inline-flex"
                onMouseEnter={(event: MouseEvent<HTMLElement>) => show(event.currentTarget)}
                onMouseLeave={hide}
                onFocus={(event: FocusEvent<HTMLElement>) => show(event.currentTarget)}
                onBlur={hide}
                onTouchStart={(event: TouchEvent<HTMLElement>) => {
                    const element = event.currentTarget;
                    window.clearTimeout(pressTimer.current);
                    pressTimer.current = window.setTimeout(() => {
                        show(element);
                        hideTimer.current = window.setTimeout(hide, TOUCH_VISIBLE_MS);
                    }, LONG_PRESS_MS);
                }}
                onTouchEnd={() => window.clearTimeout(pressTimer.current)}
                onTouchMove={() => window.clearTimeout(pressTimer.current)}
            >
                {children}
            </span>
            {anchor &&
                createPortal(
                    <div
                        ref={tooltipRef}
                        role="tooltip"
                        className="pointer-events-none fixed z-999999 max-w-64 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-center text-xs font-medium text-gray-700 shadow-theme-md dark:border-gray-800 dark:bg-gray-dark dark:text-gray-300"
                        style={{
                            left: anchor.center,
                            top: above ? anchor.top - 8 : anchor.bottom + 8,
                            transform: above ? 'translate(-50%, -100%)' : 'translate(-50%, 0)',
                        }}
                    >
                        {label}
                    </div>,
                    document.body,
                )}
        </>
    );
};
