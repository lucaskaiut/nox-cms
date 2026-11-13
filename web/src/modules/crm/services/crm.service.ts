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

  async moveLead(leadId: string, stageId: string): Promise<Lead> {
    return (await http.post<ApiResponse<Lead>>(`/leads/${leadId}/move`, { stage_id: stageId })).data.data
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
}
