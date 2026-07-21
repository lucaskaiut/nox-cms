import type { Permission } from '@/shared/constants/permissions'

export interface Role {
  id: number
  name: string
  description: string | null
  is_default: boolean
  permissions?: Permission[]
}

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  roles?: Role[]
  created_at: string | null
  updated_at: string | null
}

export interface Tenant {
  id: string
  name: string
  document: string
  email: string
  phone: string | null
  domain: string
  created_at: string | null
  updated_at: string | null
}

export interface ApiToken {
  id: number
  name: string
  permissions: string[] | null
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

export interface Webhook {
  id: number
  name: string
  url: string
  method: string
  event: string
  headers: Record<string, string> | null
  query_params: Record<string, string> | null
  body_template: Record<string, unknown> | null
  is_active: boolean
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export interface WebhookLog {
  id: number
  status_code: number | null
  response_body: string | null
  request_payload: unknown
  error_message: string | null
  duration_ms: number | null
  created_at: string | null
}

export interface Session {
  user: User
  tenant: Tenant
  roles: Role[]
  permissions: Permission[]
}
