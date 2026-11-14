import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'

export type LeadStatus = 'new' | 'qualified' | 'won' | 'lost' | 'spam'

export interface Lead {
  id: string
  ref: string
  channel: 'whatsapp' | 'form' | string
  name: string | null
  email: string | null
  phone: string | null
  company: string | null
  message: string | null
  status: LeadStatus
  status_label: string
  gclid: string | null
  gbraid: string | null
  wbraid: string | null
  utm_source: string | null
  utm_medium: string | null
  utm_campaign: string | null
  utm_term: string | null
  utm_content: string | null
  landing_path: string | null
  click_path: string | null
  consent: 'granted' | 'denied' | 'unknown'
  clicked_at: string | null
  contacted_at: string | null
  qualified_at: string | null
  conversion_value: string | null
  notes: string | null
  created_at: string | null
  stage?: LeadStageRef | null
  stage_changed_at?: string | null
  last_activity_at?: string | null
  conversation?: LeadConversationRef | null
  source?: LeadSource
  cta?: string | null
  lost_reason?: string | null
  lost_at?: string | null
  won_at?: string | null
  email_opt_out?: boolean
  post?: { id: string; title: string; slug: string } | null
  tags?: LeadTagRef[]
  open_tasks_count?: number
  conversion_uploads?: Array<{ kind: 'qualified' | 'won'; status: 'pending' | 'uploaded' | 'failed'; error: string | null; uploaded_at: string | null }>
}

export type LeadSource = 'paid' | 'blog' | 'referral' | 'whatsapp' | 'direct' | 'manual'

export const LEAD_SOURCE_LABELS: Record<LeadSource, string> = {
  paid: 'Anúncio',
  blog: 'Blog',
  referral: 'Campanha',
  whatsapp: 'WhatsApp',
  direct: 'Site',
  manual: 'Manual',
}

export interface LeadTagRef {
  id: string
  name: string
  color: string
}

export interface LeadStageRef {
  id: string
  name: string
  color: string
  outcome: 'open' | 'qualified' | 'won' | 'lost'
}

export interface LeadConversationRef {
  id: string
  unread_count: number
  last_inbound_at: string | null
  last_message_at: string | null
}

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
  new: 'Novo',
  qualified: 'Qualificado',
  won: 'Fechado',
  lost: 'Perdido',
  spam: 'Spam',
}

export const LEAD_CHANNEL_LABELS: Record<string, string> = {
  whatsapp: 'WhatsApp',
  form: 'Formulário',
  manual: 'Cadastro manual',
}

export const LEAD_STATUS_OPTIONS = Object.entries(LEAD_STATUS_LABELS).map(([value, label]) => ({ value, label }))

export interface LeadFilters extends ListParams {
  status?: string
  ref?: string
  tag?: string
  post?: string
}

export interface LeadPayload {
  status?: LeadStatus
  notes?: string | null
  conversion_value?: number | null
  name?: string | null
  email?: string | null
  phone?: string | null
  company?: string | null
  lost_reason?: string | null
}

export const leadsService = {
  async list(filters: LeadFilters): Promise<PaginatedResponse<Lead>> {
    const response = await http.get<PaginatedResponse<Lead>>('/leads', { params: filters })

    return response.data
  },

  async get(id: string): Promise<Lead> {
    const response = await http.get<ApiResponse<Lead>>(`/leads/${id}`)

    return response.data.data
  },

  async update(id: string, payload: LeadPayload): Promise<Lead> {
    const response = await http.patch<ApiResponse<Lead>>(`/leads/${id}`, payload)

    return response.data.data
  },

  async syncTags(id: string, tags: string[]): Promise<Lead> {
    const response = await http.put<ApiResponse<Lead>>(`/leads/${id}/tags`, { tags })

    return response.data.data
  },

  async exportGoogleAds(): Promise<Blob> {
    const response = await http.get<Blob>('/leads/export/google-ads', { responseType: 'blob' })

    return response.data
  },
}
