import { useMemo, useState, type FormEvent } from 'react'
import { RefreshCw } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Field, Input, Modal, Select, Spinner } from '@/shared/design-system'
import { queryKeys } from '@/shared/constants/query-keys'
import { useSendTemplate, useTemplatesQuery } from '../hooks/useWhatsApp'
import { whatsappService, type Conversation, type Message } from '../services/whatsapp.service'

interface TemplateModalProps {
  conversation: Conversation
  onClose: () => void
  onSent: (message: Message) => void
}

const fill = (text: string, values: Record<string, string>) =>
  text.replace(/\{\{\s*(\w+)\s*\}\}/g, (match, key: string) => values[key] || match)

/** Template aprovado: único jeito de falar com quem não escreveu nas últimas 24h. */
export function TemplateModal({ conversation, onClose, onSent }: TemplateModalProps) {
  const queryClient = useQueryClient()
  const templates = useTemplatesQuery(true)
  const send = useSendTemplate(conversation.id)
  const [selected, setSelected] = useState('')
  const [header, setHeader] = useState<Record<string, string>>({})
  const [body, setBody] = useState<Record<string, string>>({})
  const [refreshing, setRefreshing] = useState(false)

  const template = useMemo(
    () => templates.data?.find((item) => `${item.name}|${item.language}` === selected),
    [templates.data, selected],
  )
  const firstName = (conversation.lead?.name ?? conversation.contact_name ?? '').split(' ')[0]

  const choose = (value: string) => {
    setSelected(value)
    const next = templates.data?.find((item) => `${item.name}|${item.language}` === value)
    // Sugestão: primeira variável costuma ser o nome do contato.
    setHeader({})
    setBody(next?.body_params[0] && firstName ? { [next.body_params[0]]: firstName } : {})
  }

  const refresh = async () => {
    setRefreshing(true)
    try {
      queryClient.setQueryData(queryKeys.whatsapp.templates(), await whatsappService.templates(true))
    } finally {
      setRefreshing(false)
    }
  }

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (!template) return
    send.mutate({ name: template.name, language: template.language, header, body }, { onSuccess: (message) => onSent(message) })
  }

  const params = template ? [...template.header_params.map((p) => ['header', p] as const), ...template.body_params.map((p) => ['body', p] as const)] : []
  const complete = params.every(([part, key]) => (part === 'header' ? header[key] : body[key])?.trim())

  return (
    <Modal
      open
      size="lg"
      onClose={onClose}
      title="Enviar template"
      description="Templates aprovados na Meta. Necessários para iniciar a conversa ou retomá-la depois de 24h sem resposta do contato."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="template-form" loading={send.isPending} disabled={!template?.supported || !complete}>
            Enviar
          </Button>
        </>
      }
    >
      {templates.isPending ? (
        <div className="flex justify-center py-6">
          <Spinner />
        </div>
      ) : templates.isError ? (
        <Alert variant="danger" title="Não foi possível carregar os templates">
          {templates.error.message}
        </Alert>
      ) : (
        <form id="template-form" onSubmit={submit} className="space-y-4">
          <div className="flex items-end gap-2">
            <Field label="Template" htmlFor="tpl-name" className="flex-1">
              <Select
                id="tpl-name"
                value={selected}
                onChange={(e) => choose(e.target.value)}
                placeholder={templates.data?.length ? 'Escolha um template' : 'Nenhum template aprovado'}
                options={(templates.data ?? []).map((item) => ({
                  value: `${item.name}|${item.language}`,
                  label: `${item.name} (${item.language})${item.supported ? '' : ' — não suportado'}`,
                }))}
              />
            </Field>
            <Button variant="secondary" onClick={refresh} loading={refreshing} aria-label="Atualizar lista de templates">
              <RefreshCw className="size-4" />
            </Button>
          </div>

          {template && !template.supported && (
            <Alert variant="warning" title="Template com mídia no cabeçalho">
              Templates com imagem, vídeo ou documento ainda não podem ser enviados pelo painel.
            </Alert>
          )}

          {template?.supported && (
            <>
              {params.map(([part, key]) => (
                <Field key={`${part}-${key}`} label={`${part === 'header' ? 'Cabeçalho' : 'Corpo'}: {{${key}}}`} htmlFor={`tpl-${part}-${key}`}>
                  <Input
                    id={`tpl-${part}-${key}`}
                    value={(part === 'header' ? header[key] : body[key]) ?? ''}
                    onChange={(e) =>
                      part === 'header'
                        ? setHeader((v) => ({ ...v, [key]: e.target.value }))
                        : setBody((v) => ({ ...v, [key]: e.target.value }))
                    }
                    maxLength={part === 'header' ? 60 : 1024}
                  />
                </Field>
              ))}

              <div>
                <p className="mb-1.5 text-xs font-medium text-muted">Prévia</p>
                <div className="rounded-xl bg-surface-2 p-3 text-sm whitespace-pre-wrap text-foreground">
                  {template.header && <p className="mb-1 font-semibold">{fill(template.header, header)}</p>}
                  <p>{fill(template.body, body)}</p>
                  {template.footer && <p className="mt-1 text-xs text-muted">{template.footer}</p>}
                </div>
              </div>
            </>
          )}
        </form>
      )}
    </Modal>
  )
}
