import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { reportsService, type AdSpendPayload } from '../services/reports.service'

export function useFunnelReport(params: { from?: string; to?: string }, enabled = true) {
  return useQuery({ queryKey: queryKeys.reports.funnel(params), queryFn: () => reportsService.funnel(params), placeholderData: keepPreviousData, enabled })
}

export function useContentReport(params: { from?: string; to?: string }) {
  return useQuery({ queryKey: queryKeys.reports.content(params), queryFn: () => reportsService.content(params), placeholderData: keepPreviousData })
}

export function useStalePosts() {
  return useQuery({ queryKey: queryKeys.reports.stalePosts(), queryFn: reportsService.stalePosts })
}

export function usePostPerformance(id: string | undefined) {
  return useQuery({ queryKey: queryKeys.reports.postPerformance(id ?? ''), queryFn: () => reportsService.postPerformance(id!), enabled: !!id })
}

export function useLinkSuggestions(id: string | undefined) {
  return useQuery({ queryKey: queryKeys.reports.linkSuggestions(id ?? ''), queryFn: () => reportsService.linkSuggestions(id!), enabled: !!id })
}

export function useAdSpends() {
  return useQuery({ queryKey: queryKeys.reports.adSpends(), queryFn: reportsService.adSpends })
}

export function useSaveAdSpend() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AdSpendPayload & { id?: string }) => reportsService.saveAdSpend(payload),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.reports.all })
      toast.success(id ? 'Lançamento atualizado' : 'Investimento registrado')
    },
    onError: (error: ApiError) => toast.error('Não foi possível salvar', error.message),
  })
}

export function useDeleteAdSpend() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: reportsService.deleteAdSpend,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.reports.all }),
    onError: (error: ApiError) => toast.error('Não foi possível remover', error.message),
  })
}
