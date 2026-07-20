import axios from 'axios'
import { API_BASE_URL } from '@/shared/api/base-url'
import { parseApiError } from '@/shared/api/errors'
import { useSessionStore } from '@/shared/stores/session.store'
import { toast } from '@/shared/stores/toast.store'

const GUEST_ENDPOINTS = ['/auth/login', '/auth/register', '/auth/me']

/**
 * Instância central de HTTP.
 * Autenticação via cookie HttpOnly (Sanctum stateful) — nenhum token
 * é armazenado no navegador. O cookie XSRF-TOKEN é anexado pelo Axios.
 */
export const http = axios.create({
  baseURL: `${API_BASE_URL}/api`,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = parseApiError(error)
    const url = String(error?.config?.url ?? '')
    const isGuestEndpoint = GUEST_ENDPOINTS.some((endpoint) => url.includes(endpoint))

    if (apiError.status === 401 && !isGuestEndpoint) {
      const store = useSessionStore.getState()

      if (store.status === 'authenticated') {
        store.setGuest()
        toast.warning('Sessão expirada', 'Faça login novamente para continuar.')
      }
    }

    if (apiError.status === 403) {
      toast.error('Acesso negado', apiError.message)
    }

    if (apiError.status >= 500) {
      toast.error('Erro no servidor', apiError.message)
    }

    return Promise.reject(apiError)
  },
)
