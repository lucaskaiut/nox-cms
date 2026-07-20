/**
 * URL base da API.
 * Em dev, usa caminho relativo (proxy do Vite encaminha).
 * Em produção (Vercel), deve ser a URL absoluta da API
 * definida em VITE_API_BASE_URL.
 *
 * Exemplo: VITE_API_BASE_URL=https://cms-api.noxtecnologias.com.br
 */
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || ''
