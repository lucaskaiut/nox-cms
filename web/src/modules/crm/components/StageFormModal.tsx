import { useState, type FormEvent } from 'react'
import { Check } from 'lucide-react'
import { Button, Field, Input, Modal, Select } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { OUTCOME_OPTIONS, STAGE_COLORS } from '../constants'
import { useSaveStage } from '../hooks/useCrm'
import type { PipelineStage, StageOutcome } from '../services/crm.service'

export function StageFormModal({ stage, onClose }: { stage?: PipelineStage; onClose: () => void }) {
  const save = useSaveStage()
  const [name, setName] = useState(stage?.name ?? '')
  const [color, setColor] = useState(stage?.color ?? 'blue')
  const [outcome, setOutcome] = useState<StageOutcome>(stage?.outcome ?? 'open')
  const errors = save.error?.fieldErrors ?? {}

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate({ id: stage?.id, name: name.trim(), color, outcome }, { onSuccess: onClose })
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={stage ? 'Editar etapa' : 'Nova etapa'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="stage-form" loading={save.isPending}>
            Salvar
          </Button>
        </>
      }
    >
      <form id="stage-form" onSubmit={submit} className="space-y-4">
        <Field label="Nome" htmlFor="stage-name" required error={errors.name?.[0]}>
          <Input id="stage-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={60} required autoFocus />
        </Field>

        <fieldset>
          <legend className="mb-2 text-sm font-medium text-foreground">Cor</legend>
          <div className="flex flex-wrap gap-2">
            {Object.entries(STAGE_COLORS).map(([value, item]) => (
              <label key={value} className="cursor-pointer" title={item.label}>
                <input
                  type="radio"
                  name="stage-color"
                  value={value}
                  checked={color === value}
                  onChange={() => setColor(value)}
                  className="peer sr-only"
                />
                <span
                  className={cn(
                    'flex size-8 items-center justify-center rounded-full text-white ring-offset-2 ring-offset-surface peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                    item.dot,
                    color === value && 'ring-2 ring-foreground',
                  )}
                >
                  {color === value && <Check className="size-4" aria-hidden="true" />}
                  <span className="sr-only">{item.label}</span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>

        <Field
          label="Significado"
          htmlFor="stage-outcome"
          hint={OUTCOME_OPTIONS.find((option) => option.value === outcome)?.hint}
        >
          <Select
            id="stage-outcome"
            value={outcome}
            onChange={(e) => setOutcome(e.target.value as StageOutcome)}
            options={OUTCOME_OPTIONS.map(({ value, label }) => ({ value, label }))}
          />
        </Field>
      </form>
    </Modal>
  )
}
