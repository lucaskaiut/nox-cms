import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { leadsService } from '@/modules/leads/services/leads.service'
import { crmService, type BoardStage, type CrmSettingsPayload, type ManualLeadPayload, type StagePayload, type TaskPayload } from '../services/crm.service'

const onError = (title: string) => (error: ApiError) => toast.error(title, error.message)

export function useStagesQuery() {
  return useQuery({ queryKey: queryKeys.pipeline.stages(), queryFn: crmService.stages })
}

export function useBoardQuery(search: string) {
  return useQuery({
    queryKey: queryKeys.pipeline.board(search),
    queryFn: () => crmService.board(search),
    refetchInterval: 30_000,
    refetchIntervalInBackground: false,
  })
}

function useInvalidatePipeline() {
  const queryClient = useQueryClient()

  return () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
    queryClient.invalidateQueries({ queryKey: queryKeys.leads.all })
  }
}

export function useSaveStage() {
  const invalidate = useInvalidatePipeline()

  return useMutation({
    mutationFn: ({ id, ...payload }: StagePayload & { id?: string }) =>
      id ? crmService.updateStage(id, payload) : crmService.createStage(payload),
    onSuccess: (_, { id }) => {
      invalidate()
      toast.success(id ? 'Etapa atualizada' : 'Etapa criada')
    },
    onError: onError('Não foi possível salvar a etapa'),
  })
}

export function useReorderStages() {
  const invalidate = useInvalidatePipeline()

  return useMutation({
    mutationFn: crmService.reorderStages,
    onSuccess: invalidate,
    onError: onError('Não foi possível reordenar'),
  })
}

export function useDeleteStage() {
  const invalidate = useInvalidatePipeline()

  return useMutation({
    mutationFn: ({ id, moveTo }: { id: string; moveTo?: string | null }) => crmService.deleteStage(id, moveTo),
    onSuccess: () => {
      invalidate()
      toast.success('Etapa removida')
    },
    onError: onError('Não foi possível remover a etapa'),
  })
}

/** Move o cartão na hora (otimista) e confirma com o servidor. */
export function useMoveLead(search: string) {
  const queryClient = useQueryClient()
  const key = queryKeys.pipeline.board(search)

  return useMutation({
    mutationFn: ({ leadId, stageId, lostReason }: { leadId: string; stageId: string; lostReason?: string | null }) =>
      crmService.moveLead(leadId, stageId, lostReason),
    onMutate: async ({ leadId, stageId }) => {
      await queryClient.cancelQueries({ queryKey: key })
      const previous = queryClient.getQueryData<BoardStage[]>(key)
      if (previous) {
        const lead = previous.flatMap((stage) => stage.leads).find((item) => item.id === leadId)
        if (lead) {
          queryClient.setQueryData<BoardStage[]>(
            key,
            previous.map((stage) => {
              const without = stage.leads.filter((item) => item.id !== leadId)
              const had = without.length !== stage.leads.length
              if (stage.id === stageId) return { ...stage, leads: [lead, ...without], total: stage.total + (had ? 0 : 1) }
              return had ? { ...stage, leads: without, total: stage.total - 1 } : stage
            }),
          )
        }
      }

      return { previous }
    },
    onError: (error: ApiError, _vars, context) => {
      if (context?.previous) queryClient.setQueryData(key, context.previous)
      toast.error('Não foi possível mover o lead', error.message)
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.leads.all })
    },
  })
}

export function useCreateLead() {
  const invalidate = useInvalidatePipeline()

  return useMutation({
    mutationFn: (payload: ManualLeadPayload) => crmService.createLead(payload),
    onSuccess: (lead) => {
      invalidate()
      toast.success('Lead criado', `${lead.name} entrou no funil (${lead.ref}).`)
    },
    onError: onError('Não foi possível criar o lead'),
  })
}

export function useActivitiesQuery(leadId: string) {
  return useQuery({ queryKey: queryKeys.pipeline.activities(leadId), queryFn: () => crmService.activities(leadId) })
}

export function useAddNote(leadId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (body: string) => crmService.addNote(leadId, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.activities(leadId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
    },
    onError: onError('Não foi possível salvar a anotação'),
  })
}

export function useLostReasonsQuery() {
  return useQuery({ queryKey: queryKeys.crm.lostReasons(), queryFn: crmService.lostReasons, staleTime: 5 * 60_000 })
}

export function useTagsQuery() {
  return useQuery({ queryKey: queryKeys.crm.tags(), queryFn: crmService.tags })
}

export function useSyncTags(leadId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (tags: string[]) => leadsService.syncTags(leadId, tags),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.crm.tags() })
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.leads.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.nurture.leadEnrollments(leadId) })
    },
    onError: onError('Não foi possível salvar as tags'),
  })
}

export function useUpdateTag() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: { id: string; name?: string; color?: string }) => crmService.updateTag(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.crm.tags() }),
    onError: onError('Não foi possível salvar a tag'),
  })
}

export function useDeleteTag() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: crmService.deleteTag,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.crm.tags() })
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
      toast.success('Tag removida')
    },
    onError: onError('Não foi possível remover a tag'),
  })
}

export function useTasksQuery(params: { scope?: string; status?: string; lead?: string }, enabled = true) {
  return useQuery({ queryKey: queryKeys.tasks.list(params), queryFn: () => crmService.tasks(params), enabled })
}

export function useStaleLeadsQuery() {
  return useQuery({ queryKey: queryKeys.tasks.stale(), queryFn: crmService.staleLeads })
}

export function useAssigneesQuery() {
  return useQuery({ queryKey: queryKeys.tasks.assignees(), queryFn: crmService.assignees, staleTime: 5 * 60_000 })
}

function useInvalidateTasks() {
  const queryClient = useQueryClient()

  return () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.tasks.all })
    queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
  }
}

export function useCreateTask(leadId: string) {
  const invalidate = useInvalidateTasks()

  return useMutation({
    mutationFn: (payload: TaskPayload) => crmService.createTask(leadId, payload),
    onSuccess: () => {
      invalidate()
      toast.success('Tarefa criada')
    },
    onError: onError('Não foi possível criar a tarefa'),
  })
}

export function useUpdateTask() {
  const invalidate = useInvalidateTasks()

  return useMutation({
    mutationFn: ({ id, ...payload }: TaskPayload & { id: string }) => crmService.updateTask(id, payload),
    onSuccess: invalidate,
    onError: onError('Não foi possível atualizar a tarefa'),
  })
}

export function useDeleteTask() {
  const invalidate = useInvalidateTasks()

  return useMutation({
    mutationFn: crmService.deleteTask,
    onSuccess: invalidate,
    onError: onError('Não foi possível remover a tarefa'),
  })
}

export function useCrmSettingsQuery() {
  return useQuery({ queryKey: queryKeys.crm.settings(), queryFn: crmService.settings })
}

export function useSaveCrmSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CrmSettingsPayload) => crmService.saveSettings(payload),
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.crm.settings(), data)
      queryClient.invalidateQueries({ queryKey: queryKeys.crm.lostReasons() })
      toast.success('Configurações salvas')
    },
    onError: onError('Não foi possível salvar'),
  })
}

export function useTestNotification() {
  return useMutation({
    mutationFn: crmService.testNotification,
    onSuccess: ({ channels }) => toast.success('Alerta de teste enviado', `Canais: ${channels.join(', ')}`),
    onError: onError('Falha no teste'),
  })
}
