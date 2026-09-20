export const isNavPathActive = (pathname: string, path: string): boolean =>
    path === '/' ? pathname === '/' : pathname === path || pathname.startsWith(`${path}/`);
