import '@tanstack/react-query'
import type { ApiError } from './errors'

/** O interceptor do Axios sempre rejeita com ApiError (status, message, fieldErrors). */
declare module '@tanstack/react-query' {
  interface Register {
    defaultError: ApiError
  }
}
