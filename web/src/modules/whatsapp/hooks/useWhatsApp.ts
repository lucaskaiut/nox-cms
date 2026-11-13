import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ApiError } from '@/shared/api/errors'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { whatsappService, type TemplatePayload, type WhatsAppAccountPayload } from '../services/whatsapp.service'

const onError = (title: string) => (error: ApiError) => toast.error(title, error.message)

export function useWhatsAppAccount() {
  return useQuery({ queryKey: queryKeys.whatsapp.account(), queryFn: whatsappService.account })
}

export function useSaveWhatsAppAccount() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: WhatsAppAccountPayload) => whatsappService.saveAccount(payload),
    onSuccess: (account) => {
      queryClient.setQueryData(queryKeys.whatsapp.account(), account)
      toast.success('Configuração salva', 'Agora clique em "Testar e ativar".')
    },
    onError: onError('Não foi possível salvar'),
  })
}

export function useToggleWhatsApp() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (active: boolean) => (active ? whatsappService.activate() : whatsappService.deactivate()),
    onSuccess: (account) => {
      queryClient.setQueryData(queryKeys.whatsapp.account(), account)
      toast.success(account.is_active ? 'WhatsApp conectado' : 'WhatsApp desativado', account.verified_name ?? undefined)
    },
    onError: onError('Falha na conexão com a Meta'),
  })
}

/** Badge de não lidas no menu: consulta leve a cada 20s. */
export function useUnreadQuery() {
  const { can } = usePermissions()

  return useQuery({
    queryKey: queryKeys.whatsapp.unread(),
    queryFn: whatsappService.unread,
    enabled: can(Permission.WHATSAPP_READ),
    refetchInterval: 20_000,
    refetchIntervalInBackground: true,
    retry: false,
  })
}

export function useConversationsQuery(params: { search: string; unread: boolean }) {
  return useQuery({
    queryKey: queryKeys.whatsapp.conversations(params),
    queryFn: () => whatsappService.conversations(params),
    refetchInterval: 8_000,
  })
}

export function useTemplatesQuery(enabled: boolean) {
  return useQuery({ queryKey: queryKeys.whatsapp.templates(), queryFn: () => whatsappService.templates(), enabled, staleTime: 5 * 60_000 })
}

export function useSendTemplate(conversationId: string) {
  return useMutation({
    mutationFn: (payload: TemplatePayload) => whatsappService.sendTemplate(conversationId, payload),
    onError: onError('Template não enviado'),
  })
}

export function useCreateLeadFromConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: whatsappService.createLead,
    onSuccess: (lead) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.pipeline.all })
      toast.success('Lead adicionado ao funil', `${lead.name} (${lead.ref})`)
    },
    onError: onError('Não foi possível criar o lead'),
  })
}

export function useStartConversation() {
  return useMutation({
    mutationFn: whatsappService.startForLead,
    onError: onError('Não foi possível abrir a conversa'),
  })
}
