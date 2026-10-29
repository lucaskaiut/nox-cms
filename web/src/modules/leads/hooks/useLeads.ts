import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { leadsService, type LeadFilters, type LeadPayload } from '../services/leads.service'

export function useLeadsQuery(filters: LeadFilters) {
  return useQuery({
    queryKey: queryKeys.leads.list(filters),
    queryFn: () => leadsService.list(filters),
    placeholderData: keepPreviousData,
  })
}

export function useUpdateLead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: LeadPayload & { id: string }) => leadsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.leads.all })
      toast.success('Lead atualizado', 'O status foi salvo.')
    },
  })
}

export function useExportGoogleAds() {
  return useMutation({
    mutationFn: leadsService.exportGoogleAds,
    onSuccess: (blob) => {
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `google-ads-leads-qualificados-${new Date().toISOString().slice(0, 10)}.csv`
      link.click()
      URL.revokeObjectURL(url)
      toast.success('Arquivo exportado', 'Envie em Google Ads > Metas > Conversões > Uploads.')
    },
  })
}
