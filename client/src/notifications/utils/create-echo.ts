import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { API_PREFIX, API_ROOT } from '../../shared/constants/api';
import { readCookie } from '../../shared/utils/read-cookie';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

type ChannelAuthorizationParams = { socketId: string; channelName: string };
type ChannelAuthorizationCallback = (error: Error | null, authData: { auth: string; channel_data?: string; shared_secret?: string } | null) => void;

/**
 * El navegador autoriza el canal privado con la cookie de sesión de Sanctum (SPA en otro origen):
 * pusher-js no envía credenciales por sí solo, así que se hace con fetch y el XSRF vigente en cada intento.
 */
async function authorizeChannel({ socketId, channelName }: ChannelAuthorizationParams, callback: ChannelAuthorizationCallback): Promise<void> {
    try {
        const response = await fetch(`${API_ROOT}${API_PREFIX}/broadcasting/auth`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
            },
            body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
        });

        if (!response.ok) {
            throw new Error(`Autorización del canal rechazada (${response.status}).`);
        }

        callback(null, await response.json());
    } catch (caught) {
        callback(caught instanceof Error ? caught : new Error('No se pudo autorizar el canal.'), null);
    }
}

export function createEcho(): Echo<'reverb'> {
    window.Pusher = Pusher;

    const port = Number(import.meta.env.VITE_REVERB_PORT);

    return new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: port,
        wssPort: port,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            endpoint: `${API_ROOT}${API_PREFIX}/broadcasting/auth`,
            transport: 'ajax',
            customHandler: authorizeChannel,
        },
    });
}
