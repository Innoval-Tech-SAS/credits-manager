/**
 * Limpia toda cookie visible desde JS (p. ej. `XSRF-TOKEN`) tras un 401. La cookie de sesión
 * de Laravel es httpOnly y no se puede tocar desde aquí, pero ya quedó inválida en el
 * servidor — el próximo login la reemplaza vía `Set-Cookie` al regenerar la sesión.
 */
export function clearSessionCookies(): void {
    document.cookie.split(';').forEach((entry) => {
        const name = entry.split('=')[0]?.trim();
        if (name) {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
        }
    });
}
