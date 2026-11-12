import { useState } from 'react'
import { Button, Field, Input, Modal, Select, Textarea } from '@/shared/design-system'
import { useUpdateLead } from '../hooks/useLeads'
import { LEAD_STATUS_OPTIONS, type Lead, type LeadStatus } from '../services/leads.service'

interface LeadEditModalProps {
  lead: Lead
  onClose: () => void
}

/** Número digitado no site → link do WhatsApp (assume Brasil quando vier sem o 55). */
function whatsappUrl(phone: string): string {
  const digits = phone.replace(/\D/g, '')
  return `https://wa.me/${digits.length <= 11 ? `55${digits}` : digits}`
}

/** Montado com `key={lead.id}`: o estado inicial vem sempre do lead aberto. */
export function LeadEditModal({ lead, onClose }: LeadEditModalProps) {
  const updateLead = useUpdateLead()
  const [status, setStatus] = useState<LeadStatus>(lead.status)
  const [value, setValue] = useState(lead.conversion_value ?? '')
  const [notes, setNotes] = useState(lead.notes ?? '')

  const save = () => {
    updateLead.mutate(
      {
        id: lead.id,
        status,
        notes: notes.trim() || null,
        conversion_value: value === '' ? null : Number(value),
      },
      { onSuccess: onClose },
    )
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={`Lead ${lead.ref}`}
      description={
        lead.channel === 'form'
          ? 'Marque como qualificado quando o contato tiver potencial real de projeto.'
          : 'O registro nasce no clique do botão: confirme que a mensagem com este código chegou no WhatsApp. Marque como qualificado quando a conversa tiver potencial real de projeto.'
      }
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={save} loading={updateLead.isPending}>
            Salvar
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {lead.channel === 'form' && (
          <dl className="space-y-2 rounded-lg border border-surface-3 p-3 text-sm">
            <div>
              <dt className="text-xs text-muted">Nome</dt>
              <dd className="text-foreground">
                {lead.name}
                {lead.company ? ` · ${lead.company}` : ''}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-muted">Contato</dt>
              <dd className="flex flex-wrap gap-x-3 text-foreground">
                {lead.phone && (
                  <a className="underline" href={whatsappUrl(lead.phone)} target="_blank" rel="noreferrer">
                    {lead.phone}
                  </a>
                )}
                {lead.email && <a className="underline" href={`mailto:${lead.email}`}>{lead.email}</a>}
              </dd>
            </div>
            <div>
              <dt className="text-xs text-muted">Mensagem</dt>
              <dd className="whitespace-pre-line text-foreground">{lead.message}</dd>
            </div>
          </dl>
        )}
        <Field label="Status" htmlFor="lead-status">
          <Select
            id="lead-status"
            options={LEAD_STATUS_OPTIONS}
            value={status}
            onChange={(e) => setStatus(e.target.value as LeadStatus)}
          />
        </Field>
        <Field
          label="Valor estimado (R$)"
          htmlFor="lead-value"
          hint="Opcional. Vai junto na conversão offline para o Google Ads."
        >
          <Input
            id="lead-value"
            type="number"
            min={0}
            step="0.01"
            value={value}
            onChange={(e) => setValue(e.target.value)}
          />
        </Field>
        <Field label="Observações" htmlFor="lead-notes">
          <Textarea id="lead-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} />
        </Field>
      </div>
    </Modal>
  )
}
