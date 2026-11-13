import { AlertCircle, Check, CheckCheck, Clock, LayoutTemplate } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import type { Message } from '../services/whatsapp.service'
import { MediaAttachment } from './MediaAttachment'

const time = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' })

function StatusIcon({ message }: { message: Message }) {
  const label = { pending: 'Enviando', sent: 'Enviada', delivered: 'Entregue', read: 'Lida', failed: 'Falhou', received: '' }[message.status]
  const Icon = { pending: Clock, sent: Check, delivered: CheckCheck, read: CheckCheck, failed: AlertCircle, received: Check }[message.status]

  return <Icon className={cn('size-3.5', message.status === 'read' && 'text-sky-300')} aria-label={label} />
}

export function MessageBubble({ message }: { message: Message }) {
  const outgoing = message.direction === 'out'
  const showBody = message.body && !(message.has_media && message.type === 'document' && message.body === message.media_filename)

  return (
    <div className={cn('flex', outgoing ? 'justify-end' : 'justify-start')}>
      <div
        className={cn(
          'max-w-[85%] rounded-2xl px-3.5 py-2 text-sm shadow-card sm:max-w-[70%]',
          outgoing ? 'rounded-br-md bg-primary text-primary-foreground' : 'rounded-bl-md bg-surface text-foreground',
          message.status === 'failed' && 'bg-danger-soft text-danger',
        )}
      >
        {message.type === 'template' && (
          <p className="mb-1 flex items-center gap-1 text-[11px] opacity-75">
            <LayoutTemplate className="size-3" aria-hidden="true" />
            Template {message.template_name}
          </p>
        )}
        {message.has_media && <MediaAttachment message={message} />}
        {message.type === 'reaction' ? (
          <p className="text-xl">{message.body}</p>
        ) : (
          showBody && <p className="break-words whitespace-pre-wrap">{message.body}</p>
        )}
        {message.error && <p className="mt-1 text-xs">{message.error}</p>}
        <p className={cn('mt-1 flex items-center justify-end gap-1 text-[11px]', outgoing ? 'opacity-80' : 'text-subtle')}>
          {outgoing && message.user && <span className="truncate">{message.user.name} ·</span>}
          {time.format(new Date(message.sent_at))}
          {outgoing && <StatusIcon message={message} />}
        </p>
      </div>
    </div>
  )
}
