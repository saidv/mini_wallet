import { ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useAuthStore } from '@/stores/auth';

// Declare Pusher on window
declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

window.Pusher = Pusher;

interface MoneyReceivedEvent {
  transaction_uuid: string;
  amount: number;
  direction: 'in' | 'out';
  new_balance: number;
}

interface PusherError {
  type: string;
  error: string;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
let echoInstance: any = null;

export function usePusher() {
  const authStore = useAuthStore();
  const connected = ref(false);
  const error = ref<string | null>(null);

  const initializeEcho = () => {
    if (echoInstance) {
      return echoInstance;
    }

    try {
      const token = localStorage.getItem('auth_token');

      echoInstance = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu',
        forceTLS: true,
        encrypted: true,
        authEndpoint: `${import.meta.env.VITE_API_BASE_URL}/broadcasting/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        },
        enabledTransports: ['ws', 'wss'],
      });

      console.log('Pusher Echo initialized', {
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      });
    } catch (err: unknown) {
      const errorMessage = err instanceof Error ? err.message : 'Pusher initialization failed';
      error.value = errorMessage;
      console.error('Pusher initialization failed:', err);
    }

    return echoInstance;
  };

  const subscribeToUserChannel = (callback: (event: MoneyReceivedEvent) => void) => {
    if (!authStore.user?.id) {
      console.warn('Cannot subscribe: User not authenticated');
      return null;
    }

    const echo = initializeEcho();
    if (!echo) return null;

    const channelName = `user.${authStore.user.id}`;

    console.log(`Subscribing to private channel: ${channelName}`);

    const channel = echo
      .private(channelName)
      .listen('money.received', (event: MoneyReceivedEvent) => {
        console.log('Money received event:', event);
        callback(event);
      })
      .error((error: PusherError) => {
        console.error('Channel error:', error);
      });

    return channel;
  };

  const disconnect = () => {
    if (echoInstance) {
      echoInstance.disconnect();
      echoInstance = null;
      connected.value = false;
      console.log('🔌 Pusher disconnected');
    }
  };

  return {
    connected,
    error,
    initializeEcho,
    subscribeToUserChannel,
    disconnect,
  };
}
