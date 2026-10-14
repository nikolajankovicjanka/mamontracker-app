import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import api from '@/lib/axios'

type AuthorizationData = {
  auth: string
}

type AuthorizationCallback = (
    error: Error | null,
    data: AuthorizationData | null,
) => void

const useTLS =
    (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'

window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'reverb',

  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,

  wsPort: Number(
      import.meta.env.VITE_REVERB_PORT ?? (useTLS ? 443 : 80),
  ),

  wssPort: Number(
      import.meta.env.VITE_REVERB_PORT ?? (useTLS ? 443 : 80),
  ),

  forceTLS: useTLS,
  enabledTransports: ['ws', 'wss'],

  authorizer: (channel: { name: string }) => ({
    authorize: (
        socketId: string,
        callback: AuthorizationCallback,
    ): void => {
      api.post<AuthorizationData>('/api/broadcasting/auth', {
        socket_id: socketId,
        channel_name: channel.name,
      })
          .then(({ data }) => {
            callback(null, data)
          })
          .catch((error: unknown) => {
            callback(
                error instanceof Error
                    ? error
                    : new Error('Autorizacija kanala nije uspjela.'),
                null,
            )
          })
    },
  }),
})