import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { Lead } from '@/modules/leads/services/leads.service'

export interface WhatsAppAccount {
  phone_number_id: string | null
  waba_id: string | null
  display_phone: string | null
  verified_name: string | null
  graph_version: string
  verify_token: string
  webhook_url: string
  has_access_token: boolean
  has_app_secret: boolean
  is_active: boolean
  verified_at: string | null
  last_webhook_at: string | null
  quality_rating?: string | null
}

export interface WhatsAppAccountPayload {
  phone_number_id: string
  waba_id: string
  access_token?: string
  app_secret?: string
  verify_token: string
  graph_version: string
}

export interface ConversationLead {
  id: string
  ref: string
  name: string | null
  company: string | null
  channel: string
  status: string
  stage: { id: string; name: string; color: string; outcome: string } | null
  has_click_id: boolean
}

export interface Conversation {
  id: string
  wa_id: string
  phone: string
  contact_name: string | null
  last_message_preview: string | null
  last_message_at: string | null
  last_inbound_at: string | null
  unread_count: number
  window_open: boolean
  window_expires_at: string | null
  lead: ConversationLead | null
}

export type MessageStatus = 'pending' | 'sent' | 'delivered' | 'read' | 'failed' | 'received'

export interface Message {
  id: string
  direction: 'in' | 'out'
  type: string
  body: string | null
  has_media: boolean
  media_mime: string | null
  media_filename: string | null
  template_name: string | null
  status: MessageStatus
  error: string | null
  user: { id: string; name: string } | null
  sent_at: string
}

export interface MessagesResponse {
  data: Message[]
  statuses: Array<{ id: string; status: MessageStatus; error: string | null }>
  has_more: boolean
  conversation: Conversation
}

export interface WhatsAppTemplate {
  name: string
  language: string
  category: string
  header: string | null
  body: string
  footer: string | null
  header_params: string[]
  body_params: string[]
  supported: boolean
}

export interface TemplatePayload {
  name: string
  language: string
  header: Record<string, string>
  body: Record<string, string>
}

export const whatsappService = {
  async account(): Promise<WhatsAppAccount> {
    return (await http.get<ApiResponse<WhatsAppAccount>>('/whatsapp/account')).data.data
  },

  async saveAccount(payload: WhatsAppAccountPayload): Promise<WhatsAppAccount> {
    return (await http.put<ApiResponse<WhatsAppAccount>>('/whatsapp/account', payload)).data.data
  },

  async activate(): Promise<WhatsAppAccount> {
    return (await http.post<ApiResponse<WhatsAppAccount>>('/whatsapp/account/activate')).data.data
  },

  async deactivate(): Promise<WhatsAppAccount> {
    return (await http.post<ApiResponse<WhatsAppAccount>>('/whatsapp/account/deactivate')).data.data
  },

  async unread(): Promise<{ conversations: number; messages: number }> {
    return (await http.get<ApiResponse<{ conversations: number; messages: number }>>('/whatsapp/unread')).data.data
  },

  async conversations(params: { search?: string; unread?: boolean; page?: number }): Promise<PaginatedResponse<Conversation>> {
    return (
      await http.get<PaginatedResponse<Conversation>>('/whatsapp/conversations', {
        params: { search: params.search || undefined, unread: params.unread ? 1 : undefined, page: params.page, per_page: 50 },
      })
    ).data
  },

  async messages(conversationId: string, params: { before?: string; after?: string } = {}): Promise<MessagesResponse> {
    return (await http.get<MessagesResponse>(`/whatsapp/conversations/${conversationId}/messages`, { params })).data
  },

  async markRead(conversationId: string): Promise<Conversation> {
    return (await http.post<ApiResponse<Conversation>>(`/whatsapp/conversations/${conversationId}/read`)).data.data
  },

  async send(conversationId: string, body: string): Promise<Message> {
    return (await http.post<ApiResponse<Message>>(`/whatsapp/conversations/${conversationId}/messages`, { body })).data.data
  },

  async templates(fresh = false): Promise<WhatsAppTemplate[]> {
    return (await http.get<ApiResponse<WhatsAppTemplate[]>>('/whatsapp/templates', { params: { fresh: fresh ? 1 : undefined } })).data.data
  },

  async sendTemplate(conversationId: string, payload: TemplatePayload): Promise<Message> {
    return (await http.post<ApiResponse<Message>>(`/whatsapp/conversations/${conversationId}/templates`, payload)).data.data
  },

  async createLead(conversationId: string): Promise<Lead> {
    return (await http.post<ApiResponse<Lead>>(`/whatsapp/conversations/${conversationId}/lead`)).data.data
  },

  async startForLead(leadId: string): Promise<Conversation> {
    return (await http.post<ApiResponse<Conversation>>(`/leads/${leadId}/whatsapp`)).data.data
  },

  async media(messageId: string): Promise<Blob> {
    return (await http.get<Blob>(`/whatsapp/messages/${messageId}/media`, { responseType: 'blob' })).data
  },
}
