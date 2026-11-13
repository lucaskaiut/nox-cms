import { useState, type FormEvent } from 'react'
import { Button, Field, Input, Modal, Select, Textarea } from '@/shared/design-system'
import { useCreateLead } from '../hooks/useCrm'
import type { PipelineStage } from '../services/crm.service'

interface NewLeadModalProps {
  stages: PipelineStage[]
  defaultStageId?: string
  onClose: () => void
}

export function NewLeadModal({ stages, defaultStageId, onClose }: NewLeadModalProps) {
  const createLead = useCreateLead()
  const [form, setForm] = useState({
    name: '',
    phone: '',
    email: '',
    company: '',
    message: '',
    stage_id: defaultStageId ?? stages[0]?.id ?? '',
  })
  const errors = createLead.error?.fieldErrors ?? {}
  const set = (key: keyof typeof form) => (event: { target: { value: string } }) =>
    setForm((current) => ({ ...current, [key]: event.target.value }))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    createLead.mutate(
      {
        name: form.name.trim(),
        phone: form.phone.trim() || null,
        email: form.email.trim() || null,
        company: form.company.trim() || null,
        message: form.message.trim() || null,
        stage_id: form.stage_id || null,
      },
      { onSuccess: onClose },
    )
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="Novo lead"
      description="Para contatos que chegaram por indicação, telefone ou outro canal."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" form="new-lead-form" loading={createLead.isPending}>
            Criar lead
          </Button>
        </>
      }
    >
      <form id="new-lead-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <Field label="Nome" htmlFor="nl-name" required error={errors.name?.[0]} className="sm:col-span-2">
          <Input id="nl-name" value={form.name} onChange={set('name')} required maxLength={120} autoFocus />
        </Field>
        <Field label="WhatsApp / telefone" htmlFor="nl-phone" error={errors.phone?.[0]}>
          <Input id="nl-phone" value={form.phone} onChange={set('phone')} placeholder="(41) 99999-0000" inputMode="tel" />
        </Field>
        <Field label="E-mail" htmlFor="nl-email" error={errors.email?.[0]}>
          <Input id="nl-email" type="email" value={form.email} onChange={set('email')} />
        </Field>
        <Field label="Empresa" htmlFor="nl-company">
          <Input id="nl-company" value={form.company} onChange={set('company')} maxLength={120} />
        </Field>
        <Field label="Etapa" htmlFor="nl-stage">
          <Select id="nl-stage" value={form.stage_id} onChange={set('stage_id')} options={stages.map((s) => ({ value: s.id, label: s.name }))} />
        </Field>
        <Field label="O que precisa" htmlFor="nl-message" className="sm:col-span-2">
          <Textarea id="nl-message" rows={3} value={form.message} onChange={set('message')} maxLength={2000} />
        </Field>
      </form>
    </Modal>
  )
}
