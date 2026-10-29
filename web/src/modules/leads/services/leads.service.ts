import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'

export type LeadStatus = 'new' | 'qualified' | 'won' | 'lost' | 'spam'

export interface Lead {
  id: string
  ref: string
  channel: string
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
}

export const LEAD_STATUS_LABELS: Record<LeadStatus, string> = {
  new: 'Novo',
  qualified: 'Qualificado',
  won: 'Fechado',
  lost: 'Perdido',
  spam: 'Spam',
}

export const LEAD_STATUS_OPTIONS = Object.entries(LEAD_STATUS_LABELS).map(([value, label]) => ({ value, label }))

export interface LeadFilters extends ListParams {
  status?: string
  ref?: string
}

export interface LeadPayload {
  status?: LeadStatus
  notes?: string | null
  conversion_value?: number | null
}

export const leadsService = {
  async list(filters: LeadFilters): Promise<PaginatedResponse<Lead>> {
    const response = await http.get<PaginatedResponse<Lead>>('/leads', { params: filters })

    return response.data
  },

  async update(id: string, payload: LeadPayload): Promise<Lead> {
    const response = await http.patch<ApiResponse<Lead>>(`/leads/${id}`, payload)

    return response.data.data
  },

  async exportGoogleAds(): Promise<Blob> {
    const response = await http.get<Blob>('/leads/export/google-ads', { responseType: 'blob' })

    return response.data
  },
}
