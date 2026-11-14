import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { nurtureService, type SequencePayload } from '../services/nurture.service'

const onError = (title: string) => (error: ApiError) => toast.error(title, error.message)

export function useSequencesQuery(enabled = true) {
  return useQuery({ queryKey: queryKeys.nurture.sequences(), queryFn: nurtureService.sequences, enabled })
}

export function useSequenceQuery(id: string | undefined) {
  return useQuery({ queryKey: queryKeys.nurture.sequence(id ?? ''), queryFn: () => nurtureService.sequence(id!), enabled: !!id })
}

export function useSequenceEnrollmentsQuery(id: string | undefined) {
  return useQuery({ queryKey: queryKeys.nurture.sequenceEnrollments(id ?? ''), queryFn: () => nurtureService.sequenceEnrollments(id!), enabled: !!id })
}

export function useSaveSequence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: SequencePayload & { id?: string }) => (id ? nurtureService.update(id, payload) : nurtureService.create(payload)),
    onSuccess: (sequence, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.nurture.all })
      queryClient.setQueryData(queryKeys.nurture.sequence(sequence.id), sequence)
      toast.success(id ? 'Sequência salva' : 'Sequência criada')
    },
    onError: onError('Não foi possível salvar a sequência'),
  })
}

export function useToggleSequence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, is_active }: { id: string; is_active: boolean }) => nurtureService.update(id, { is_active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.nurture.all }),
    onError: onError('Não foi possível alterar a sequência'),
  })
}

export function useDeleteSequence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: nurtureService.remove,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.nurture.all })
      toast.success('Sequência removida')
    },
    onError: onError('Não foi possível remover'),
  })
}

export function useTestStep(id: string) {
  return useMutation({
    mutationFn: (step: number) => nurtureService.test(id, step),
    onSuccess: () => toast.success('E-mail de teste enviado', 'Confira sua caixa de entrada.'),
    onError: onError('Não foi possível enviar o teste'),
  })
}

export function useLeadEnrollmentsQuery(leadId: string) {
  return useQuery({ queryKey: queryKeys.nurture.leadEnrollments(leadId), queryFn: () => nurtureService.leadEnrollments(leadId) })
}

export function useEnrollLead(leadId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (sequenceId: string) => nurtureService.enroll(leadId, sequenceId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.nurture.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.activities(leadId) })
      toast.success('Lead inscrito na sequência')
    },
    onError: onError('Não foi possível inscrever'),
  })
}

export function useStopEnrollment(leadId?: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: nurtureService.stop,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.nurture.all })
      if (leadId) queryClient.invalidateQueries({ queryKey: queryKeys.nurture.leadEnrollments(leadId) })
      toast.success('Sequência interrompida')
    },
    onError: onError('Não foi possível interromper'),
  })
}
