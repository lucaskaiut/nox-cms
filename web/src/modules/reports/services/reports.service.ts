import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface FunnelTotals {
  leads: number
  qualified: number
  won: number
  lost: number
  revenue: number
  avg_ticket: number | null
  win_rate: number | null
  avg_days_to_win: number | null
  open_pipeline_value: number
}

export interface FunnelReport {
  period: { from: string; to: string; days: number }
  previous_period: { from: string; to: string }
  totals: FunnelTotals
  previous: FunnelTotals
  series: Array<{ date: string; leads: number; won: number }>
  cohort: { created: number; qualified: number; won: number }
  by_source: Array<{ source: string; label: string; leads: number; qualified: number; won: number; revenue: number }>
  lost_reasons: Array<{ reason: string; count: number; value: number }>
  stages: Array<{ id: string; name: string; color: string; outcome: string; count: number; value: number; avg_days: number | null; samples: number }>
  spend: {
    total: number
    paid_leads: number
    paid_won: number
    paid_revenue: number
    cost_per_lead: number | null
    cost_per_won: number | null
    roas: number | null
  }
}

export interface ContentRow {
  id: string
  title: string
  slug: string
  published_at: string | null
  updated_at: string | null
  cta_offer: string | null
  categories: string[]
  tags: string[]
  leads: number
  qualified: number
  won: number
  revenue: number
  clicks: number | null
  impressions: number | null
  position: number | null
  clicks_previous: number | null
  lead_rate: number | null
}

export interface ContentGroup {
  name: string
  posts: number
  leads: number
  won: number
  revenue: number
  clicks: number
}

export interface ContentReport {
  period: { from: string; to: string }
  posts: ContentRow[]
  categories: ContentGroup[]
  tags: ContentGroup[]
}

export interface StalePost {
  id: string
  title: string
  slug: string
  updated_at: string | null
  reasons: Array<{ type: 'old' | 'traffic_drop' | 'striking_distance' | 'no_leads'; label: string }>
  clicks_28d: number
  clicks_prev_28d: number
}

export interface PostPerformance {
  leads: number
  qualified: number
  won: number
  revenue: number
  offers: Array<{ offer: string; leads: number }>
  recent_leads: Array<{ id: string; ref: string; name: string | null; status: string; status_label: string; created_at: string }>
  search: null | { days: number; clicks: number; impressions: number; position: number | null; series: Array<{ date: string; clicks: number; impressions: number }> }
}

export interface LinkSuggestion {
  id: string
  title: string
  slug: string
  url: string | null
  score: number
  leads: number
  shared_terms: string[]
  already_linked: boolean
  links_here: boolean
}

export interface AdSpend {
  id: string
  channel: string
  channel_label: string
  period_start: string
  period_end: string
  amount: number
  notes: string | null
}

export type AdSpendPayload = Omit<AdSpend, 'id' | 'channel_label'>

export const AD_CHANNELS = [
  { value: 'google_ads', label: 'Google Ads' },
  { value: 'meta_ads', label: 'Meta Ads' },
  { value: 'linkedin_ads', label: 'LinkedIn Ads' },
  { value: 'other', label: 'Outro' },
]

type Period = { from?: string; to?: string }

export const reportsService = {
  async funnel(params: Period): Promise<FunnelReport> {
    return (await http.get<ApiResponse<FunnelReport>>('/reports/funnel', { params })).data.data
  },
  async content(params: Period): Promise<ContentReport> {
    return (await http.get<ApiResponse<ContentReport>>('/reports/content', { params })).data.data
  },
  async stalePosts(): Promise<StalePost[]> {
    return (await http.get<ApiResponse<StalePost[]>>('/reports/stale-posts')).data.data
  },
  async postPerformance(id: string): Promise<PostPerformance> {
    return (await http.get<ApiResponse<PostPerformance>>(`/posts/${id}/performance`)).data.data
  },
  async linkSuggestions(id: string): Promise<{ link_to: LinkSuggestion[]; link_from: LinkSuggestion[] }> {
    return (await http.get<ApiResponse<{ link_to: LinkSuggestion[]; link_from: LinkSuggestion[] }>>(`/posts/${id}/link-suggestions`)).data.data
  },
  async adSpends(): Promise<AdSpend[]> {
    return (await http.get<ApiResponse<AdSpend[]>>('/ad-spends')).data.data
  },
  async saveAdSpend(payload: AdSpendPayload & { id?: string }): Promise<AdSpend> {
    const { id, ...body } = payload
    return id
      ? (await http.put<ApiResponse<AdSpend>>(`/ad-spends/${id}`, body)).data.data
      : (await http.post<ApiResponse<AdSpend>>('/ad-spends', body)).data.data
  },
  async deleteAdSpend(id: string): Promise<void> {
    await http.delete(`/ad-spends/${id}`)
  },
}
