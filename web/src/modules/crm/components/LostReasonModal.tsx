import { useState, type FormEvent } from 'react'
import { Button, Field, Input, Modal } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { useLostReasonsQuery } from '../hooks/useCrm'

interface LostReasonModalProps {
  leadName: string
  loading?: boolean
  onConfirm: (reason: string | null) => void
  onClose: () => void
}

/** Pergunta o motivo da perda ao mover o lead para uma etapa "Perdido". */
export function LostReasonModal({ leadName, loading, onConfirm, onClose }: LostReasonModalProps) {
  const reasons = useLostReasonsQuery()
  const [selected, setSelected] = useState<string | null>(null)
  const [other, setOther] = useState('')
  const reason = other.trim() || selected

  const submit = (event: FormEvent) => {
    event.preventDefault()
    onConfirm(reason)
  }

  return (
    <Modal
      open
      onClose={onClose}
      size="sm"
      title="Por que o negócio foi perdido?"
      description={`${leadName}. O motivo aparece no relatório de perdas.`}
    >
      <form onSubmit={submit} className="space-y-4">
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Motivo da perda">
          {(reasons.data ?? []).map((item) => (
            <button
              key={item}
              type="button"
              role="radio"
              aria-checked={selected === item && !other.trim()}
              onClick={() => {
                setSelected(item)
                setOther('')
              }}
              className={cn(
                'rounded-full border px-3 py-1.5 text-sm transition-colors',
                selected === item && !other.trim()
                  ? 'border-primary bg-primary-soft text-primary'
                  : 'border-surface-3 text-foreground hover:bg-surface-2',
              )}
            >
              {item}
            </button>
          ))}
        </div>
        <Field label="Outro motivo" htmlFor="lost-other">
          <Input id="lost-other" value={other} maxLength={120} onChange={(e) => setOther(e.target.value)} placeholder="Descreva, se não estiver na lista" />
        </Field>
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button variant="ghost" onClick={() => onConfirm(null)} disabled={loading}>
            Pular
          </Button>
          <Button type="submit" loading={loading} disabled={!reason}>
            Marcar como perdido
          </Button>
        </div>
      </form>
    </Modal>
  )
}
