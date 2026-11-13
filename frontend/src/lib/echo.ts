import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { env } from '@/config/env';
import { useAuthStore } from '@/stores/authStore';

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

window.Pusher = Pusher;

let echoInstance: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> {
  if (echoInstance) {
    return echoInstance;
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: env.reverbKey,
    wsHost: env.reverbHost,
    wsPort: env.reverbPort,
    wssPort: env.reverbPort,
    forceTLS: env.reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${env.apiUrl.replace('/api/v1', '')}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${useAuthStore.getState().token ?? ''}`,
      },
    },
  });

  return echoInstance;
}

export function resetEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
}
