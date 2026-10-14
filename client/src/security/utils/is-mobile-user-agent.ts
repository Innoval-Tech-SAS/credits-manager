/** Heurística simple sobre el `user_agent` crudo: suficiente para elegir un ícono, no para detección robusta. */
export function isMobileUserAgent(userAgent: string | null): boolean {
    if (!userAgent) {
        return false;
    }

    return /Mobi|Android|iPhone|iPad|iPod/i.test(userAgent);
}
