import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { crmService, type BoardStage, type ManualLeadPayload, type StagePayload } from '../services/crm.service'

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
    mutationFn: ({ leadId, stageId }: { leadId: string; stageId: string }) => crmService.moveLead(leadId, stageId),
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
