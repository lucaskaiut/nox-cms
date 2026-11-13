import { useCallback, useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Clock, IdCard, LayoutTemplate, SendHorizontal, UserPlus } from 'lucide-react'
import { Badge, Button, Spinner, Textarea } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { formatDateTime, formatRelative } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'
import type { ApiError } from '@/shared/api/errors'
import { leadsService } from '@/modules/leads/services/leads.service'
import { LeadDetailModal } from '@/modules/crm/components/LeadDetailModal'
import { stageColor } from '@/modules/crm/constants'
import { useStagesQuery } from '@/modules/crm/hooks/useCrm'
import { useCreateLeadFromConversation } from '../hooks/useWhatsApp'
import { whatsappService, type Conversation, type Message } from '../services/whatsapp.service'
import { conversationTitle } from '../utils/conversation'
import { MessageBubble } from './MessageBubble'
import { TemplateModal } from './TemplateModal'

const POLL_MS = 4000
const dayFormatter = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })

function LeadSheet({ leadId, onClose }: { leadId: string; onClose: () => void }) {
  const lead = useQuery({ queryKey: queryKeys.leads.detail(leadId), queryFn: () => leadsService.get(leadId) })
  const stages = useStagesQuery()
  if (!lead.data || !stages.data) return null

  return <LeadDetailModal lead={lead.data} stages={stages.data} search="" onClose={onClose} />
}

/** Conversa aberta: histórico, envio de texto dentro da janela de 24h e templates fora dela. */
export function ChatPanel({ conversationId }: { conversationId: string }) {
  const queryClient = useQueryClient()
  const [messages, setMessages] = useState<Message[]>([])
  const [conversation, setConversation] = useState<Conversation | null>(null)
  const [hasMore, setHasMore] = useState(false)
  const [loading, setLoading] = useState(true)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [draft, setDraft] = useState('')
  const [templateOpen, setTemplateOpen] = useState(false)
  const [sheetOpen, setSheetOpen] = useState(false)
  const scrollRef = useRef<HTMLDivElement>(null)
  const stickToBottom = useRef(true)
  const createLead = useCreateLeadFromConversation()
  const { can } = usePermissions()

  const lastServerId = messages.filter((m) => !m.id.startsWith('tmp-')).at(-1)?.id

  const scrollToBottom = () => {
    requestAnimationFrame(() => {
      const el = scrollRef.current
      if (el) el.scrollTop = el.scrollHeight
    })
  }

  const markRead = useCallback(async () => {
    try {
      const updated = await whatsappService.markRead(conversationId)
      setConversation(updated)
      queryClient.invalidateQueries({ queryKey: queryKeys.whatsapp.unread() })
      queryClient.invalidateQueries({ queryKey: ['whatsapp', 'conversations'] })
    } catch {
      // silencioso: o polling tenta de novo
    }
  }, [conversationId, queryClient])

  // Carga inicial ao trocar de conversa.
  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setMessages([])
    setDraft('')
    whatsappService
      .messages(conversationId)
      .then((response) => {
        if (cancelled) return
        setMessages(response.data)
        setHasMore(response.has_more)
        setConversation(response.conversation)
        stickToBottom.current = true
        scrollToBottom()
        if (response.conversation.unread_count > 0) void markRead()
      })
      .catch((error: ApiError) => !cancelled && toast.error('Conversa indisponível', error.message))
      .finally(() => !cancelled && setLoading(false))

    return () => {
      cancelled = true
    }
  }, [conversationId, markRead])

  // Polling: só busca mensagens novas e atualiza status de entrega.
  useEffect(() => {
    if (loading) return
    const timer = window.setInterval(async () => {
      if (document.hidden) return
      try {
        const response = await whatsappService.messages(conversationId, lastServerId ? { after: lastServerId } : {})
        const statuses = new Map(response.statuses.map((s) => [s.id, s]))
        setMessages((current) => {
          const known = new Set(current.map((m) => m.id))
          const updated = current.map((m) => {
            const s = statuses.get(m.id)
            return s && (s.status !== m.status || s.error !== m.error) ? { ...m, status: s.status, error: s.error } : m
          })
          const fresh = response.data.filter((m) => !known.has(m.id))
          return fresh.length ? [...updated, ...fresh] : updated
        })
        setConversation(response.conversation)
        if (response.data.some((m) => m.direction === 'in')) {
          if (stickToBottom.current) scrollToBottom()
          void markRead()
        }
      } catch {
        // rede instável: próxima rodada
      }
    }, POLL_MS)

    return () => window.clearInterval(timer)
  }, [conversationId, lastServerId, loading, markRead])

  const loadOlder = async () => {
    const first = messages.find((m) => !m.id.startsWith('tmp-'))
    if (!first) return
    setLoadingOlder(true)
    const el = scrollRef.current
    const previousHeight = el?.scrollHeight ?? 0
    try {
      const response = await whatsappService.messages(conversationId, { before: first.id })
      setMessages((current) => [...response.data, ...current])
      setHasMore(response.has_more)
      requestAnimationFrame(() => {
        if (el) el.scrollTop = el.scrollHeight - previousHeight
      })
    } finally {
      setLoadingOlder(false)
    }
  }

  const appendSent = (message: Message, tempId?: string) => {
    setMessages((current) => (tempId ? current.map((m) => (m.id === tempId ? message : m)) : [...current, message]))
    stickToBottom.current = true
    scrollToBottom()
    queryClient.invalidateQueries({ queryKey: ['whatsapp', 'conversations'] })
  }

  const send = async (event?: FormEvent) => {
    event?.preventDefault()
    const body = draft.trim()
    if (!body) return
    const tempId = `tmp-${Date.now()}`
    setDraft('')
    appendSent({
      id: tempId, direction: 'out', type: 'text', body, has_media: false, media_mime: null, media_filename: null,
      template_name: null, status: 'pending', error: null, user: null, sent_at: new Date().toISOString(),
    })
    try {
      appendSent(await whatsappService.send(conversationId, body), tempId)
    } catch (error) {
      const apiError = error as ApiError
      setMessages((current) => current.map((m) => (m.id === tempId ? { ...m, status: 'failed', error: apiError.message } : m)))
      toast.error('Mensagem não enviada', apiError.message)
    }
  }

  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
      event.preventDefault()
      void send()
    }
  }

  if (loading || !conversation) {
    return (
      <div className="flex h-full items-center justify-center">
        <Spinner />
      </div>
    )
  }

  const lead = conversation.lead
  const windowOpen = conversation.window_open

  return (
    <div className="flex h-full min-h-0 flex-col">
      <header className="flex flex-wrap items-center gap-3 border-b border-surface-3 px-4 py-3">
        <Link to="/conversas" className="rounded-lg p-1.5 text-muted hover:bg-surface-2 lg:hidden" aria-label="Voltar para a lista">
          <ArrowLeft className="size-5" />
        </Link>
        <div className="min-w-0 flex-1">
          <h2 className="truncate font-semibold text-foreground">{conversationTitle(conversation)}</h2>
          <p className="truncate text-xs text-muted">
            {conversation.phone}
            {conversation.contact_name && lead?.name && conversation.contact_name !== lead.name && ` · no WhatsApp: ${conversation.contact_name}`}
          </p>
        </div>
        {lead ? (
          <Button variant="secondary" size="sm" onClick={() => setSheetOpen(true)}>
            {lead.stage && <span className={cn('size-2 rounded-full', stageColor(lead.stage.color).dot)} aria-hidden="true" />}
            {lead.stage?.name ?? 'Lead'}
            <span className="font-mono text-xs text-muted">{lead.ref}</span>
            <IdCard className="size-4" />
          </Button>
        ) : (
          <Can permission={Permission.LEAD_UPDATE}>
            <Button
              variant="secondary"
              size="sm"
              loading={createLead.isPending}
              onClick={() => createLead.mutate(conversationId, { onSuccess: () => void markRead() })}
            >
              <UserPlus className="size-4" />
              Adicionar ao funil
            </Button>
          </Can>
        )}
      </header>

      <div
        ref={scrollRef}
        onScroll={(e) => {
          const el = e.currentTarget
          stickToBottom.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80
        }}
        className="min-h-0 flex-1 space-y-2 overflow-y-auto bg-surface-2/50 px-3 py-4 sm:px-6"
      >
        {hasMore && (
          <div className="flex justify-center">
            <Button variant="ghost" size="sm" loading={loadingOlder} onClick={loadOlder}>
              Carregar mensagens anteriores
            </Button>
          </div>
        )}
        {messages.length === 0 && (
          <p className="py-10 text-center text-sm text-muted">
            Nenhuma mensagem ainda. Para iniciar a conversa, envie um template aprovado.
          </p>
        )}
        {messages.map((message, index) => {
          const day = dayFormatter.format(new Date(message.sent_at))
          const previous = messages[index - 1]
          const newDay = !previous || dayFormatter.format(new Date(previous.sent_at)) !== day

          return (
            <div key={message.id}>
              {newDay && (
                <p className="my-3 text-center">
                  <span className="rounded-full bg-surface px-3 py-1 text-[11px] text-muted shadow-card">{day}</span>
                </p>
              )}
              <MessageBubble message={message} />
            </div>
          )
        })}
      </div>

      {!can(Permission.WHATSAPP_SEND) ? (
        <p className="border-t border-surface-3 p-3 text-center text-sm text-muted">Você pode ver, mas não responder conversas.</p>
      ) : (
        <div className="border-t border-surface-3 p-3">
          {windowOpen ? (
            <p className="mb-2 flex items-center gap-1.5 text-xs text-muted">
              <Clock className="size-3.5" aria-hidden="true" />
              Janela de atendimento aberta até {formatDateTime(conversation.window_expires_at)} ({formatRelative(conversation.window_expires_at)}).
            </p>
          ) : (
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-warning-soft px-3 py-2 text-xs text-warning">
              <span>
                {conversation.last_inbound_at
                  ? 'O contato não escreve há mais de 24h. Só é possível enviar um template aprovado.'
                  : 'O contato ainda não escreveu. Para iniciar a conversa, envie um template aprovado.'}
              </span>
              <Badge variant="warning">Janela fechada</Badge>
            </div>
          )}
          <form onSubmit={send} className="flex items-end gap-2">
            <Button variant="secondary" onClick={() => setTemplateOpen(true)} aria-label="Enviar template" title="Enviar template">
              <LayoutTemplate className="size-4" />
            </Button>
            <Textarea
              aria-label="Mensagem"
              rows={1}
              value={draft}
              disabled={!windowOpen}
              onChange={(e) => setDraft(e.target.value)}
              onKeyDown={onKeyDown}
              placeholder={windowOpen ? 'Mensagem' : 'Janela fechada: use um template'}
              title="Enter envia · Shift+Enter quebra a linha"
              maxLength={4096}
              className="max-h-40 min-h-10 flex-1 resize-none"
            />
            <Button type="submit" disabled={!windowOpen || !draft.trim()} aria-label="Enviar">
              <SendHorizontal className="size-4" />
            </Button>
          </form>
        </div>
      )}

      {templateOpen && (
        <TemplateModal
          conversation={conversation}
          onClose={() => setTemplateOpen(false)}
          onSent={(message) => {
            setTemplateOpen(false)
            appendSent(message)
            toast.success('Template enviado')
          }}
        />
      )}
      {sheetOpen && lead && <LeadSheet leadId={lead.id} onClose={() => setSheetOpen(false)} />}
    </div>
  )
}
