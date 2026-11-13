import { useState } from 'react'
import { useParams } from 'react-router'
import { MessagesSquare } from 'lucide-react'
import { EmptyState } from '@/shared/design-system'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { cn } from '@/shared/utils/cn'
import { ChatPanel } from '../components/ChatPanel'
import { ConversationList } from '../components/ConversationList'
import { useConversationsQuery } from '../hooks/useWhatsApp'

/** Caixa de entrada do WhatsApp: lista à esquerda, conversa à direita (uma coisa por vez no celular). */
export default function ConversationsPage() {
  const { id } = useParams()
  const [search, setSearch] = useState('')
  const [onlyUnread, setOnlyUnread] = useState(false)
  const debounced = useDebounce(search).trim()
  const conversations = useConversationsQuery({ search: debounced, unread: onlyUnread })

  return (
    <div className="-mx-6 -mt-2 grid h-[calc(100dvh-3.5rem)] min-h-0 border-t border-surface-3 lg:-mx-10 lg:grid-cols-[22rem_1fr]">
      <aside className={cn('min-h-0 border-surface-3 bg-surface lg:block lg:border-r', id ? 'hidden' : 'block')}>
        <ConversationList
          conversations={conversations.data?.data ?? []}
          loading={conversations.isPending}
          search={search}
          onlyUnread={onlyUnread}
          onSearch={setSearch}
          onToggleUnread={setOnlyUnread}
        />
      </aside>
      <section className={cn('min-h-0 bg-surface', id ? 'block' : 'hidden lg:block')} aria-label="Conversa">
        {id ? (
          <ChatPanel key={id} conversationId={id} />
        ) : (
          <div className="flex h-full items-center justify-center p-6">
            <EmptyState icon={MessagesSquare} title="Escolha uma conversa" description="Responda pelo painel, sem depender do celular." />
          </div>
        )}
      </section>
    </div>
  )
}
