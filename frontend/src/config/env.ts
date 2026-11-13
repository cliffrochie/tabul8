export const env = {
  apiUrl: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1',
  reverbKey: import.meta.env.VITE_REVERB_APP_KEY || 'tabul8-local-key',
  reverbHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
  reverbPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
  reverbScheme: (import.meta.env.VITE_REVERB_SCHEME || 'http') as 'http' | 'https',
} as const;
