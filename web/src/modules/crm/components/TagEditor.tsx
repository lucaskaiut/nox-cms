import { useState, type KeyboardEvent } from 'react'
import { Tag, X } from 'lucide-react'
import { stageColor } from '../constants'
import { useSyncTags, useTagsQuery } from '../hooks/useCrm'
import type { LeadTagRef } from '@/modules/leads/services/leads.service'

interface TagEditorProps {
  leadId: string
  tags: LeadTagRef[]
  editable: boolean
  onChange: (tags: LeadTagRef[]) => void
}

/** Tags do lead: segmentação e gatilho de sequências de nutrição. */
export function TagEditor({ leadId, tags, editable, onChange }: TagEditorProps) {
  const [value, setValue] = useState('')
  const all = useTagsQuery()
  const sync = useSyncTags(leadId)
  const listId = `tags-${leadId}`

  const save = (names: string[]) => sync.mutate(names, { onSuccess: (lead) => onChange(lead.tags ?? []) })

  const add = () => {
    const name = value.trim()
    if (!name || tags.some((t) => t.name.toLowerCase() === name.toLowerCase())) {
      setValue('')
      return
    }
    save([...tags.map((t) => t.name), name])
    setValue('')
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault()
      add()
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <Tag className="size-3.5 text-muted" aria-hidden="true" />
      {tags.map((tag) => (
        <span key={tag.id} className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${stageColor(tag.color).soft}`}>
          {tag.name}
          {editable && (
            <button
              type="button"
              aria-label={`Remover tag ${tag.name}`}
              className="rounded-full hover:bg-black/10"
              onClick={() => save(tags.filter((t) => t.id !== tag.id).map((t) => t.name))}
            >
              <X className="size-3" />
            </button>
          )}
        </span>
      ))}
      {editable ? (
        <>
          <input
            aria-label="Adicionar tag"
            list={listId}
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onKeyDown={onKeyDown}
            onBlur={add}
            disabled={sync.isPending}
            maxLength={60}
            placeholder={tags.length ? '+ tag' : 'Adicionar tag…'}
            className="h-7 w-28 rounded-md bg-transparent px-1.5 text-xs text-foreground placeholder:text-subtle focus:bg-surface-2"
          />
          <datalist id={listId}>
            {(all.data ?? []).filter((t) => !tags.some((x) => x.id === t.id)).map((t) => (
              <option key={t.id} value={t.name} />
            ))}
          </datalist>
        </>
      ) : (
        tags.length === 0 && <span className="text-xs text-subtle">Sem tags</span>
      )}
    </div>
  )
}
