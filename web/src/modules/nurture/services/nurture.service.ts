import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export type NurtureTrigger = 'manual' | 'tag' | 'lost'

export const TRIGGER_LABELS: Record<NurtureTrigger, string> = {
  manual: 'Manual',
  tag: 'Ao receber uma tag',
  lost: 'Reativação de perdidos',
}

export interface NurtureStep {
  id?: string
  position?: number
  delay_days: number
  subject: string
  body: string
  post: { id: string; title: string } | null
}

export interface NurtureSequence {
  id: string
  name: string
  trigger: NurtureTrigger
  trigger_tag: { id: string; name: string } | null
  trigger_delay_days: number
  is_active: boolean
  steps_count?: number
  active_count?: number
  completed_count?: number
  steps?: NurtureStep[]
}

export interface NurtureEnrollment {
  id: string
  status: 'active' | 'completed' | 'stopped'
  stopped_reason: string | null
  next_step: number
  next_run_at: string | null
  last_sent_at: string | null
  sequence: { id: string; name: string; steps_count: number | null } | null
  lead?: { id: string; ref: string; name: string | null; email: string | null } | null
  created_at: string
}

export interface SequencePayload {
  name: string
  trigger: NurtureTrigger
  trigger_tag_id?: string | null
  trigger_delay_days?: number
  is_active?: boolean
  steps: Array<{ id?: string; delay_days: number; subject: string; body: string; post_id?: string | null }>
}

export const nurtureService = {
  async sequences(): Promise<NurtureSequence[]> {
    return (await http.get<ApiResponse<NurtureSequence[]>>('/nurture/sequences')).data.data
  },
  async sequence(id: string): Promise<NurtureSequence> {
    return (await http.get<ApiResponse<NurtureSequence>>(`/nurture/sequences/${id}`)).data.data
  },
  async create(payload: SequencePayload): Promise<NurtureSequence> {
    return (await http.post<ApiResponse<NurtureSequence>>('/nurture/sequences', payload)).data.data
  },
  async update(id: string, payload: Partial<SequencePayload>): Promise<NurtureSequence> {
    return (await http.put<ApiResponse<NurtureSequence>>(`/nurture/sequences/${id}`, payload)).data.data
  },
  async remove(id: string): Promise<void> {
    await http.delete(`/nurture/sequences/${id}`)
  },
  async sequenceEnrollments(id: string): Promise<NurtureEnrollment[]> {
    return (await http.get<ApiResponse<NurtureEnrollment[]>>(`/nurture/sequences/${id}/enrollments`)).data.data
  },
  async test(id: string, step: number): Promise<void> {
    await http.post(`/nurture/sequences/${id}/test`, { step })
  },
  async leadEnrollments(leadId: string): Promise<NurtureEnrollment[]> {
    return (await http.get<ApiResponse<NurtureEnrollment[]>>(`/leads/${leadId}/enrollments`)).data.data
  },
  async enroll(leadId: string, sequenceId: string): Promise<NurtureEnrollment> {
    return (await http.post<ApiResponse<NurtureEnrollment>>(`/leads/${leadId}/enrollments`, { sequence_id: sequenceId })).data.data
  },
  async stop(enrollmentId: string): Promise<NurtureEnrollment> {
    return (await http.post<ApiResponse<NurtureEnrollment>>(`/enrollments/${enrollmentId}/stop`)).data.data
  },
}
