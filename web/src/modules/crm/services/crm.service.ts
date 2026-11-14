import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { Lead } from '@/modules/leads/services/leads.service'

export type StageOutcome = 'open' | 'qualified' | 'won' | 'lost'

export interface PipelineStage {
  id: string
  name: string
  color: string
  outcome: StageOutcome
  outcome_label: string
  position: number
  leads_count?: number
}

export interface BoardStage extends PipelineStage {
  total: number
  leads: Lead[]
}

export interface LeadActivity {
  id: string
  type: 'created' | 'note' | 'stage_changed' | 'whatsapp_linked' | string
  body: string | null
  meta: Record<string, string | null> | null
  user: { id: string; name: string } | null
  created_at: string
}

export interface StagePayload {
  name: string
  color: string
  outcome: StageOutcome
}

export interface ManualLeadPayload {
  name: string
  phone?: string | null
  email?: string | null
  company?: string | null
  message?: string | null
  stage_id?: string | null
}

export interface LeadTask {
  id: string
  title: string
  notes: string | null
  due_at: string
  completed_at: string | null
  overdue: boolean
  assignee: { id: string; name: string } | null
  lead: {
    id: string
    ref: string
    name: string | null
    company: string | null
    phone: string | null
    stage: { name: string; color: string } | null
  } | null
  created_at: string
}

export interface TaskPayload {
  title?: string
  notes?: string | null
  due_at?: string
  assigned_to?: string | null
  completed?: boolean
}

export interface LeadTag {
  id: string
  name: string
  color: string
  leads_count?: number
}

export interface CrmSettings {
  site_url: string | null
  post_url_pattern: string | null
  lost_reasons: string[]
  stale_lead_days: number
  stale_post_months: number
  notify_emails: string[]
  notify_new_lead: boolean
  notify_daily_digest: boolean
  has_telegram_bot_token: boolean
  telegram_chat_id: string | null
  auto_reply_enabled: boolean
  auto_reply_subject: string | null
  auto_reply_body: string | null
  email_signature: string | null
  template_variables: string[]
}

export type CrmSettingsPayload = Partial<Omit<CrmSettings, 'has_telegram_bot_token' | 'template_variables'>> & {
  telegram_bot_token?: string
  remove_telegram_bot_token?: boolean
}

export const crmService = {
  async stages(): Promise<PipelineStage[]> {
    return (await http.get<ApiResponse<PipelineStage[]>>('/pipeline/stages')).data.data
  },

  async createStage(payload: StagePayload): Promise<PipelineStage> {
    return (await http.post<ApiResponse<PipelineStage>>('/pipeline/stages', payload)).data.data
  },

  async updateStage(id: string, payload: Partial<StagePayload>): Promise<PipelineStage> {
    return (await http.patch<ApiResponse<PipelineStage>>(`/pipeline/stages/${id}`, payload)).data.data
  },

  async reorderStages(ids: string[]): Promise<PipelineStage[]> {
    return (await http.post<ApiResponse<PipelineStage[]>>('/pipeline/stages/reorder', { ids })).data.data
  },

  async deleteStage(id: string, moveTo?: string | null): Promise<void> {
    await http.delete(`/pipeline/stages/${id}`, { data: moveTo ? { move_to: moveTo } : {} })
  },

  async board(search?: string): Promise<BoardStage[]> {
    return (await http.get<ApiResponse<BoardStage[]>>('/pipeline/board', { params: { search: search || undefined } })).data.data
  },

  async moveLead(leadId: string, stageId: string, lostReason?: string | null): Promise<Lead> {
    return (await http.post<ApiResponse<Lead>>(`/leads/${leadId}/move`, { stage_id: stageId, lost_reason: lostReason || undefined })).data.data
  },

  async createLead(payload: ManualLeadPayload): Promise<Lead> {
    return (await http.post<ApiResponse<Lead>>('/pipeline/leads', payload)).data.data
  },

  async activities(leadId: string): Promise<LeadActivity[]> {
    return (await http.get<ApiResponse<LeadActivity[]>>(`/leads/${leadId}/activities`)).data.data
  },

  async addNote(leadId: string, body: string): Promise<LeadActivity> {
    return (await http.post<ApiResponse<LeadActivity>>(`/leads/${leadId}/notes`, { body })).data.data
  },

  async tasks(params: { scope?: string; status?: string; lead?: string }): Promise<LeadTask[]> {
    return (await http.get<ApiResponse<LeadTask[]>>('/tasks', { params })).data.data
  },

  async createTask(leadId: string, payload: TaskPayload): Promise<LeadTask> {
    return (await http.post<ApiResponse<LeadTask>>(`/leads/${leadId}/tasks`, payload)).data.data
  },

  async updateTask(id: string, payload: TaskPayload): Promise<LeadTask> {
    return (await http.patch<ApiResponse<LeadTask>>(`/tasks/${id}`, payload)).data.data
  },

  async deleteTask(id: string): Promise<void> {
    await http.delete(`/tasks/${id}`)
  },

  async staleLeads(): Promise<Lead[]> {
    return (await http.get<ApiResponse<Lead[]>>('/crm/stale-leads')).data.data
  },

  async assignees(): Promise<Array<{ id: string; name: string }>> {
    return (await http.get<ApiResponse<Array<{ id: string; name: string }>>>('/crm/assignees')).data.data
  },

  async tags(): Promise<LeadTag[]> {
    return (await http.get<ApiResponse<LeadTag[]>>('/lead-tags')).data.data
  },

  async updateTag(id: string, payload: { name?: string; color?: string }): Promise<LeadTag> {
    return (await http.patch<ApiResponse<LeadTag>>(`/lead-tags/${id}`, payload)).data.data
  },

  async deleteTag(id: string): Promise<void> {
    await http.delete(`/lead-tags/${id}`)
  },

  async lostReasons(): Promise<string[]> {
    return (await http.get<ApiResponse<string[]>>('/crm/lost-reasons')).data.data
  },

  async settings(): Promise<CrmSettings> {
    return (await http.get<ApiResponse<CrmSettings>>('/crm/settings')).data.data
  },

  async saveSettings(payload: CrmSettingsPayload): Promise<CrmSettings> {
    return (await http.put<ApiResponse<CrmSettings>>('/crm/settings', payload)).data.data
  },

  async testNotification(): Promise<{ channels: string[] }> {
    return (await http.post<ApiResponse<{ channels: string[] }>>('/crm/settings/test')).data.data
  },
}
