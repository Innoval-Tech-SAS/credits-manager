import type { FC, LazyExoticComponent } from 'react';
import { lazy } from 'react';

/**
 * `React.lazy` para páginas con export con nombre (el proyecto no usa `export default`): cada página queda en su
 * propio chunk y solo se descarga al abrirla, conservando el tipo de sus props. El `Suspense` que muestra el esqueleto
 * mientras llega está en `AppLayout`.
 */
export function lazyPage<K extends string, P extends object>(cargar: () => Promise<Record<K, FC<P>>>, nombre: K): LazyExoticComponent<FC<P>> {
    return lazy(async () => ({ default: (await cargar())[nombre] }));
}
