import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface GoogleAdsAccount {
  customer_id: string | null
  login_customer_id: string | null
  client_id: string | null
  has_developer_token: boolean
  has_client_secret: boolean
  has_refresh_token: boolean
  qualified_action_id: string | null
  won_action_id: string | null
  currency: string
  is_active: boolean
  is_configured: boolean
  last_upload_at: string | null
  last_error: string | null
  api_version: string
  counts: { pending: number; uploaded: number; failed: number }
}

export interface GoogleAdsPayload {
  customer_id?: string | null
  login_customer_id?: string | null
  developer_token?: string
  client_id?: string | null
  client_secret?: string
  refresh_token?: string
  qualified_action_id?: string | null
  won_action_id?: string | null
  currency?: string
}

export interface ConversionUpload {
  id: number
  kind: 'qualified' | 'won'
  status: 'pending' | 'uploaded' | 'failed'
  value: string | null
  conversion_at: string
  uploaded_at: string | null
  attempts: number
  error: string | null
  lead: { id: string; ref: string; name: string | null } | null
}

export interface SearchConsoleAccount {
  site_url: string | null
  service_account_email: string | null
  has_credentials: boolean
  is_active: boolean
  last_sync_at: string | null
  last_error: string | null
}

export const integrationsService = {
  async googleAds(): Promise<GoogleAdsAccount> {
    return (await http.get<ApiResponse<GoogleAdsAccount>>('/integrations/google-ads')).data.data
  },
  async saveGoogleAds(payload: GoogleAdsPayload): Promise<GoogleAdsAccount> {
    return (await http.put<ApiResponse<GoogleAdsAccount>>('/integrations/google-ads', payload)).data.data
  },
  async toggleGoogleAds(active: boolean): Promise<GoogleAdsAccount> {
    return (await http.post<ApiResponse<GoogleAdsAccount>>(`/integrations/google-ads/${active ? 'activate' : 'deactivate'}`)).data.data
  },
  async conversionActions(): Promise<Array<{ id: string; name: string; status: string }>> {
    return (await http.get<ApiResponse<Array<{ id: string; name: string; status: string }>>>('/integrations/google-ads/conversion-actions')).data.data
  },
  async uploads(status: string): Promise<ConversionUpload[]> {
    return (await http.get<ApiResponse<ConversionUpload[]>>('/integrations/google-ads/uploads', { params: { status: status || undefined } })).data.data
  },
  async retry(): Promise<{ requeued: number }> {
    return (await http.post<ApiResponse<{ requeued: number }>>('/integrations/google-ads/retry')).data.data
  },
  async searchConsole(): Promise<SearchConsoleAccount> {
    return (await http.get<ApiResponse<SearchConsoleAccount>>('/integrations/search-console')).data.data
  },
  async saveSearchConsole(payload: { site_url?: string | null; service_account_json?: string }): Promise<SearchConsoleAccount> {
    return (await http.put<ApiResponse<SearchConsoleAccount>>('/integrations/search-console', payload)).data.data
  },
  async toggleSearchConsole(active: boolean): Promise<SearchConsoleAccount> {
    return (await http.post<ApiResponse<SearchConsoleAccount>>(`/integrations/search-console/${active ? 'activate' : 'deactivate'}`)).data.data
  },
  async syncSearchConsole(): Promise<void> {
    await http.post('/integrations/search-console/sync')
  },
}
