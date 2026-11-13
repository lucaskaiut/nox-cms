import type { DragEvent } from 'react'
import { Building2, MessageCircle } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { formatRelative } from '@/shared/utils/format'
import { LEAD_CHANNEL_LABELS, type Lead } from '@/modules/leads/services/leads.service'

interface LeadCardProps {
  lead: Lead
  draggable: boolean
  dragging: boolean
  onOpen: (lead: Lead) => void
  onDragStart: (event: DragEvent, lead: Lead) => void
  onDragEnd: () => void
}

export function LeadCard({ lead, draggable, dragging, onOpen, onDragStart, onDragEnd }: LeadCardProps) {
  const unread = lead.conversation?.unread_count ?? 0
  const fromAd = Boolean(lead.gclid || lead.gbraid || lead.wbraid)
  const confirmed = Boolean(lead.conversation?.last_inbound_at)

  return (
    <button
      type="button"
      draggable={draggable}
      onDragStart={(event) => onDragStart(event, lead)}
      onDragEnd={onDragEnd}
      onClick={() => onOpen(lead)}
      className={cn(
        'group w-full rounded-lg border border-surface-3 bg-surface p-3 text-left shadow-card transition',
        'hover:border-primary/40 hover:shadow-raised focus-visible:outline-2',
        draggable && 'cursor-grab active:cursor-grabbing',
        dragging && 'opacity-40',
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <span className="min-w-0">
          <span className="block truncate text-sm font-medium text-foreground">{lead.name || 'Sem nome'}</span>
          {lead.company && (
            <span className="mt-0.5 flex items-center gap-1 truncate text-xs text-muted">
              <Building2 className="size-3 shrink-0" aria-hidden="true" />
              {lead.company}
            </span>
          )}
        </span>
        {unread > 0 && (
          <span
            className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-green-600 px-1.5 text-[11px] font-semibold text-white"
            title={`${unread} mensagem(ns) não lida(s)`}
          >
            {unread}
          </span>
        )}
      </div>

      {lead.message && <p className="mt-2 line-clamp-2 text-xs leading-relaxed text-muted">{lead.message}</p>}

      <div className="mt-2.5 flex flex-wrap items-center gap-1.5 text-[11px]">
        <span className="rounded bg-surface-2 px-1.5 py-0.5 font-mono text-muted">{lead.ref}</span>
        <span className="rounded bg-surface-2 px-1.5 py-0.5 text-muted">{LEAD_CHANNEL_LABELS[lead.channel] ?? lead.channel}</span>
        {fromAd && <span className="rounded bg-primary-soft px-1.5 py-0.5 text-primary">Anúncio</span>}
        {confirmed && (
          <span className="flex items-center gap-1 rounded bg-green-500/10 px-1.5 py-0.5 text-green-700 dark:text-green-300" title="O contato escreveu no WhatsApp">
            <MessageCircle className="size-3" aria-hidden="true" />
            Conversou
          </span>
        )}
        <span className="ml-auto text-subtle">{formatRelative(lead.last_activity_at ?? lead.created_at)}</span>
      </div>
    </button>
  )
}
