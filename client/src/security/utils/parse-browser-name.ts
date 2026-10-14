/**
 * Heurística simple sobre el `user_agent` crudo, en el orden que importa (Edge y Opera también
 * incluyen "Chrome" en su cadena; Firefox antes que Safari por "like Gecko"). Brave no se puede
 * distinguir de Chrome por user-agent (lo imita a propósito), así que queda identificado como Chrome.
 */
export function parseBrowserName(userAgent: string | null): string {
    if (!userAgent) {
        return '—';
    }

    if (/Edg\//.test(userAgent)) {
        return 'Edge';
    }

    if (/OPR\/|Opera/.test(userAgent)) {
        return 'Opera';
    }

    if (/Firefox\//.test(userAgent)) {
        return 'Firefox';
    }

    if (/Chrome\//.test(userAgent)) {
        return 'Chrome';
    }

    if (/Safari\//.test(userAgent)) {
        return 'Safari';
    }

    if (/MSIE |Trident\//.test(userAgent)) {
        return 'Internet Explorer';
    }

    return 'Desconocido';
}
