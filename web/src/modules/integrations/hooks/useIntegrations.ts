import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { integrationsService, type GoogleAdsPayload } from '../services/integrations.service'

const onError = (title: string) => (error: ApiError) => toast.error(title, error.message)

export function useGoogleAdsQuery() {
  return useQuery({ queryKey: queryKeys.integrations.googleAds(), queryFn: integrationsService.googleAds })
}

export function useSaveGoogleAds() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: GoogleAdsPayload) => integrationsService.saveGoogleAds(payload),
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.integrations.googleAds(), data)
      toast.success('Configuração salva')
    },
    onError: onError('Não foi possível salvar'),
  })
}

export function useToggleGoogleAds() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: integrationsService.toggleGoogleAds,
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.integrations.googleAds(), data)
      queryClient.invalidateQueries({ queryKey: ['integrations', 'google-ads', 'uploads'] })
      toast.success(data.is_active ? 'Google Ads conectado' : 'Envio automático desativado')
    },
    onError: onError('Falha na conexão'),
  })
}

export function useConversionActions(enabled: boolean) {
  return useQuery({ queryKey: queryKeys.integrations.googleAdsActions(), queryFn: integrationsService.conversionActions, enabled, retry: false })
}

export function useConversionUploads(status: string) {
  return useQuery({ queryKey: queryKeys.integrations.googleAdsUploads(status), queryFn: () => integrationsService.uploads(status) })
}

export function useRetryUploads() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: integrationsService.retry,
    onSuccess: ({ requeued }) => {
      queryClient.invalidateQueries({ queryKey: ['integrations', 'google-ads'] })
      toast.success(`${requeued} conversão(ões) na fila novamente`)
    },
    onError: onError('Não foi possível reenviar'),
  })
}

export function useSearchConsoleQuery() {
  return useQuery({ queryKey: queryKeys.integrations.searchConsole(), queryFn: integrationsService.searchConsole })
}

export function useSaveSearchConsole() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: integrationsService.saveSearchConsole,
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.integrations.searchConsole(), data)
      toast.success('Configuração salva')
    },
    onError: onError('Não foi possível salvar'),
  })
}

export function useToggleSearchConsole() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: integrationsService.toggleSearchConsole,
    onSuccess: (data) => {
      queryClient.setQueryData(queryKeys.integrations.searchConsole(), data)
      queryClient.invalidateQueries({ queryKey: queryKeys.reports.all })
      toast.success(data.is_active ? 'Search Console conectado' : 'Integração desativada', data.is_active ? 'A importação dos dados começou.' : undefined)
    },
    onError: onError('Falha na conexão'),
  })
}

export function useSyncSearchConsole() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: integrationsService.syncSearchConsole,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.integrations.searchConsole() })
      queryClient.invalidateQueries({ queryKey: queryKeys.reports.all })
      toast.success('Sincronização iniciada')
    },
    onError: onError('Não foi possível sincronizar'),
  })
}
