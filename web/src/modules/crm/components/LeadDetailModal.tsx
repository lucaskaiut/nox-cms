import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { ArrowRight, Mail, MessageCircle, Pencil, Phone, StickyNote } from 'lucide-react'
import { Badge, Button, Field, Input, Modal, Select, Spinner, Textarea } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime, formatRelative } from '@/shared/utils/format'
import { useUpdateLead } from '@/modules/leads/hooks/useLeads'
import { LEAD_CHANNEL_LABELS, type Lead } from '@/modules/leads/services/leads.service'
import { useStartConversation } from '@/modules/whatsapp/hooks/useWhatsApp'
import { stageColor } from '../constants'
import { useActivitiesQuery, useAddNote, useMoveLead } from '../hooks/useCrm'
import type { LeadActivity, PipelineStage } from '../services/crm.service'

interface LeadDetailModalProps {
  lead: Lead
  stages: PipelineStage[]
  search: string
  onClose: () => void
}

function activityText(activity: LeadActivity): string {
  switch (activity.type) {
    case 'created':
      return `Lead criado (${LEAD_CHANNEL_LABELS[activity.meta?.channel ?? ''] ?? activity.meta?.channel ?? 'site'})`
    case 'stage_changed':
      return activity.meta?.from ? `Movido de ${activity.meta.from} para ${activity.meta.to}` : `Colocado em ${activity.meta?.to}`
    case 'whatsapp_linked':
      return activity.body ?? 'Conversa do WhatsApp vinculada'
    default:
      return activity.body ?? ''
  }
}

/** Ficha do lead: etapa, dados de contato, origem, conversa e linha do tempo. */
export function LeadDetailModal({ lead: initial, stages, search, onClose }: LeadDetailModalProps) {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [lead, setLead] = useState(initial)
  const [editing, setEditing] = useState(false)
  const [note, setNote] = useState('')
  const [contact, setContact] = useState({
    name: lead.name ?? '',
    phone: lead.phone ?? '',
    email: lead.email ?? '',
    company: lead.company ?? '',
  })
  const activities = useActivitiesQuery(lead.id)
  const addNote = useAddNote(lead.id)
  const moveLead = useMoveLead(search)
  const updateLead = useUpdateLead()
  const startConversation = useStartConversation()
  const fromAd = Boolean(lead.gclid || lead.gbraid || lead.wbraid)

  const move = (stageId: string) =>
    moveLead.mutate({ leadId: lead.id, stageId }, { onSuccess: (updated) => setLead(updated) })

  const saveContact = (event: FormEvent) => {
    event.preventDefault()
    updateLead.mutate(
      {
        id: lead.id,
        name: contact.name.trim() || null,
        phone: contact.phone.trim() || null,
        email: contact.email.trim() || null,
        company: contact.company.trim() || null,
      },
      {
        onSuccess: () => {
          setLead((current) => ({ ...current, ...contact }))
          setEditing(false)
        },
      },
    )
  }

  const submitNote = (event: FormEvent) => {
    event.preventDefault()
    if (!note.trim()) return
    addNote.mutate(note.trim(), { onSuccess: () => setNote('') })
  }

  const openConversation = () => {
    if (lead.conversation) {
      navigate(`/conversas/${lead.conversation.id}`)
      return
    }
    startConversation.mutate(lead.id, { onSuccess: (conversation) => navigate(`/conversas/${conversation.id}`) })
  }

  const canChat = can(Permission.WHATSAPP_READ) && (lead.conversation || (lead.phone && can(Permission.WHATSAPP_SEND)))
  const color = lead.stage ? stageColor(lead.stage.color) : null

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={
        <span className="flex flex-wrap items-center gap-2">
          {lead.name || 'Lead sem nome'}
          <span className="font-mono text-sm font-normal text-muted">{lead.ref}</span>
        </span>
      }
      description={`${LEAD_CHANNEL_LABELS[lead.channel] ?? lead.channel} · criado ${formatRelative(lead.created_at)}`}
    >
      <div className="space-y-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <Field label="Etapa" htmlFor="lead-stage" className="flex-1">
            <div className="flex items-center gap-2">
              {color && <span className={`size-2.5 shrink-0 rounded-full ${color.dot}`} aria-hidden="true" />}
              <Select
                id="lead-stage"
                value={lead.stage?.id ?? ''}
                disabled={!can(Permission.LEAD_UPDATE) || moveLead.isPending}
                onChange={(event) => move(event.target.value)}
                options={[
                  ...(lead.stage ? [] : [{ value: '', label: 'Fora do funil' }]),
                  ...stages.map((stage) => ({ value: stage.id, label: stage.name })),
                ]}
              />
            </div>
          </Field>
          {canChat && (
            <Button onClick={openConversation} loading={startConversation.isPending} className="sm:w-auto">
              <MessageCircle className="size-4" />
              {lead.conversation ? 'Abrir conversa' : 'Iniciar conversa'}
              {(lead.conversation?.unread_count ?? 0) > 0 && (
                <span className="rounded-full bg-white/25 px-1.5 text-xs">{lead.conversation?.unread_count}</span>
              )}
            </Button>
          )}
        </div>

        <section aria-labelledby="lead-contact" className="rounded-lg border border-surface-3 p-4">
          <div className="flex items-center justify-between gap-2">
            <h3 id="lead-contact" className="text-sm font-semibold text-foreground">
              Contato
            </h3>
            <Can permission={Permission.LEAD_UPDATE}>
              {!editing && (
                <Button variant="ghost" size="sm" onClick={() => setEditing(true)}>
                  <Pencil className="size-3.5" />
                  Editar
                </Button>
              )}
            </Can>
          </div>

          {editing ? (
            <form onSubmit={saveContact} className="mt-3 grid gap-3 sm:grid-cols-2">
              {(['name', 'company', 'phone', 'email'] as const).map((key) => (
                <Field
                  key={key}
                  htmlFor={`lc-${key}`}
                  label={{ name: 'Nome', company: 'Empresa', phone: 'WhatsApp / telefone', email: 'E-mail' }[key]}
                  error={updateLead.error?.fieldErrors?.[key]?.[0]}
                >
                  <Input id={`lc-${key}`} value={contact[key]} onChange={(e) => setContact((c) => ({ ...c, [key]: e.target.value }))} />
                </Field>
              ))}
              <div className="flex justify-end gap-2 sm:col-span-2">
                <Button variant="secondary" size="sm" onClick={() => setEditing(false)}>
                  Cancelar
                </Button>
                <Button type="submit" size="sm" loading={updateLead.isPending}>
                  Salvar
                </Button>
              </div>
            </form>
          ) : (
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-muted">Empresa</dt>
                <dd className="text-foreground">{lead.company || '—'}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted">Telefone</dt>
                <dd className="flex items-center gap-1.5 text-foreground">
                  {lead.phone ? (
                    <>
                      <Phone className="size-3.5 text-muted" aria-hidden="true" />
                      {lead.phone}
                    </>
                  ) : (
                    '—'
                  )}
                </dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs text-muted">E-mail</dt>
                <dd className="flex items-center gap-1.5 break-all text-foreground">
                  {lead.email ? (
                    <a className="flex items-center gap-1.5 underline" href={`mailto:${lead.email}`}>
                      <Mail className="size-3.5 text-muted" aria-hidden="true" />
                      {lead.email}
                    </a>
                  ) : (
                    '—'
                  )}
                </dd>
              </div>
              {lead.message && (
                <div className="sm:col-span-2">
                  <dt className="text-xs text-muted">Mensagem</dt>
                  <dd className="whitespace-pre-line text-foreground">{lead.message}</dd>
                </div>
              )}
            </dl>
          )}
        </section>

        <section aria-labelledby="lead-origin" className="text-sm">
          <h3 id="lead-origin" className="text-sm font-semibold text-foreground">
            Origem
          </h3>
          <div className="mt-2 flex flex-wrap items-center gap-2 text-muted">
            {fromAd ? <Badge variant="primary">Google Ads</Badge> : <Badge variant="neutral">Sem anúncio</Badge>}
            {lead.landing_path && <span className="font-mono text-xs">{lead.landing_path}</span>}
            {lead.utm_term && <span className="text-xs">termo: {lead.utm_term}</span>}
            {lead.qualified_at && <span className="text-xs">qualificado em {formatDateTime(lead.qualified_at)}</span>}
          </div>
          {fromAd && !lead.gclid && (
            <p className="mt-1 text-xs text-muted">Clique de iPhone (gbraid/wbraid): não entra na exportação por GCLID.</p>
          )}
        </section>

        <section aria-labelledby="lead-history">
          <h3 id="lead-history" className="text-sm font-semibold text-foreground">
            Histórico
          </h3>

          <Can permission={Permission.LEAD_UPDATE}>
            <form onSubmit={submitNote} className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
              <Textarea
                aria-label="Nova anotação"
                rows={2}
                placeholder="Anotar ligação, reunião, próximo passo…"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                maxLength={5000}
                className="flex-1"
              />
              <Button type="submit" variant="secondary" loading={addNote.isPending} disabled={!note.trim()}>
                <StickyNote className="size-4" />
                Anotar
              </Button>
            </form>
          </Can>

          {activities.isPending ? (
            <div className="mt-4 flex justify-center">
              <Spinner />
            </div>
          ) : (
            <ol className="mt-4 space-y-3">
              {(activities.data ?? []).map((activity) => (
                <li key={activity.id} className="flex gap-3 text-sm">
                  <span className="mt-1.5 size-2 shrink-0 rounded-full bg-surface-3" aria-hidden="true" />
                  <div className="min-w-0">
                    <p className={activity.type === 'note' ? 'whitespace-pre-line text-foreground' : 'flex items-center gap-1 text-muted'}>
                      {activity.type === 'stage_changed' && <ArrowRight className="size-3.5" aria-hidden="true" />}
                      {activityText(activity)}
                    </p>
                    <p className="text-xs text-subtle">
                      {activity.user?.name ?? 'Sistema'} · {formatDateTime(activity.created_at)}
                    </p>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>
    </Modal>
  )
}
