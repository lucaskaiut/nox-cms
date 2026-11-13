import { NavLink } from 'react-router'
import { MessageSquareDashed } from 'lucide-react'
import { EmptyState, SearchInput, Spinner, Switch } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { formatRelative, getInitials } from '@/shared/utils/format'
import { stageColor } from '@/modules/crm/constants'
import type { Conversation } from '../services/whatsapp.service'
import { conversationTitle } from '../utils/conversation'

interface ConversationListProps {
  conversations: Conversation[]
  loading: boolean
  search: string
  onlyUnread: boolean
  onSearch: (value: string) => void
  onToggleUnread: (value: boolean) => void
}

export function ConversationList({ conversations, loading, search, onlyUnread, onSearch, onToggleUnread }: ConversationListProps) {
  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="space-y-3 border-b border-surface-3 p-3">
        <SearchInput placeholder="Nome, telefone, empresa ou código" value={search} onChange={(e) => onSearch(e.target.value)} />
        <label htmlFor="only-unread" className="flex cursor-pointer items-center gap-2 text-sm text-muted">
          <Switch id="only-unread" checked={onlyUnread} onCheckedChange={onToggleUnread} label="Só não lidas" />
          Só não lidas
        </label>
      </div>

      <nav aria-label="Conversas" className="min-h-0 flex-1 overflow-y-auto">
        {loading ? (
          <div className="flex justify-center p-6">
            <Spinner />
          </div>
        ) : conversations.length === 0 ? (
          <EmptyState
            icon={MessageSquareDashed}
            title={search || onlyUnread ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}
            description={search || onlyUnread ? undefined : 'As mensagens recebidas no número da Cloud API aparecem aqui.'}
          />
        ) : (
          <ul>
            {conversations.map((conversation) => (
              <li key={conversation.id}>
                <NavLink
                  to={`/conversas/${conversation.id}`}
                  className={({ isActive }) =>
                    cn(
                      'flex gap-3 border-b border-surface-3/60 px-3 py-3 transition-colors hover:bg-surface-2',
                      isActive && 'bg-primary-soft hover:bg-primary-soft',
                    )
                  }
                >
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface-3 text-sm font-semibold text-muted">
                    {getInitials(conversationTitle(conversation)) || '#'}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center justify-between gap-2">
                      <span className={cn('truncate text-sm text-foreground', conversation.unread_count > 0 && 'font-semibold')}>
                        {conversationTitle(conversation)}
                      </span>
                      <span className="shrink-0 text-[11px] text-subtle">{formatRelative(conversation.last_message_at)}</span>
                    </span>
                    <span className="mt-0.5 flex items-center justify-between gap-2">
                      <span className="truncate text-xs text-muted">{conversation.last_message_preview ?? conversation.phone}</span>
                      {conversation.unread_count > 0 && (
                        <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-green-600 px-1.5 text-[11px] font-semibold text-white">
                          {conversation.unread_count}
                        </span>
                      )}
                    </span>
                    {conversation.lead?.stage && (
                      <span className="mt-1 flex items-center gap-1.5 text-[11px] text-muted">
                        <span className={cn('size-2 rounded-full', stageColor(conversation.lead.stage.color).dot)} aria-hidden="true" />
                        {conversation.lead.stage.name}
                        <span className="font-mono text-subtle">{conversation.lead.ref}</span>
                      </span>
                    )}
                  </span>
                </NavLink>
              </li>
            ))}
          </ul>
        )}
      </nav>
    </div>
  )
}
