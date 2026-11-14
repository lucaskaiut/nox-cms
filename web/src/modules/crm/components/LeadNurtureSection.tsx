import { useState } from 'react'
import { MailCheck, Square } from 'lucide-react'
import { Badge, Button, Select } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime } from '@/shared/utils/format'
import type { Lead } from '@/modules/leads/services/leads.service'
import { useEnrollLead, useLeadEnrollmentsQuery, useSequencesQuery, useStopEnrollment } from '@/modules/nurture/hooks/useNurture'

const STATUS: Record<string, { label: string; variant: 'primary' | 'success' | 'neutral' }> = {
  active: { label: 'Ativa', variant: 'primary' },
  completed: { label: 'Concluída', variant: 'success' },
  stopped: { label: 'Interrompida', variant: 'neutral' },
}

/** Sequências de e-mail em que o lead está (ou esteve) inscrito. */
export function LeadNurtureSection({ lead }: { lead: Lead }) {
  const { can } = usePermissions()
  const enrollments = useLeadEnrollmentsQuery(lead.id)
  const sequences = useSequencesQuery(can(Permission.LEAD_UPDATE))
  const enroll = useEnrollLead(lead.id)
  const stop = useStopEnrollment(lead.id)
  const [sequenceId, setSequenceId] = useState('')

  const enrolledIds = new Set((enrollments.data ?? []).filter((e) => e.status === 'active').map((e) => e.sequence?.id))
  const options = (sequences.data ?? []).filter((s) => s.is_active && !enrolledIds.has(s.id))
  const canEmail = Boolean(lead.email) && !lead.email_opt_out

  return (
    <section aria-labelledby="lead-nurture">
      <h3 id="lead-nurture" className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
        <MailCheck className="size-4 text-muted" aria-hidden="true" />
        Nutrição por e-mail
      </h3>
      {!canEmail && (
        <p className="mt-1 text-xs text-muted">{lead.email_opt_out ? 'O lead se descadastrou dos e-mails.' : 'Cadastre um e-mail para usar sequências.'}</p>
      )}

      {(enrollments.data ?? []).length > 0 && (
        <ul className="mt-2 space-y-2">
          {(enrollments.data ?? []).map((e) => (
            <li key={e.id} className="flex flex-wrap items-center gap-2 text-sm">
              <span className="font-medium text-foreground">{e.sequence?.name}</span>
              <Badge variant={STATUS[e.status].variant}>{STATUS[e.status].label}</Badge>
              <span className="text-xs text-muted">
                {e.status === 'active' && e.next_run_at && `próximo e-mail ${formatDateTime(e.next_run_at)}`}
                {e.status === 'stopped' && e.stopped_reason}
                {e.sequence?.steps_count ? ` · ${Math.min(e.next_step, e.sequence.steps_count)}/${e.sequence.steps_count} enviados` : ''}
              </span>
              {e.status === 'active' && can(Permission.LEAD_UPDATE) && (
                <Button variant="ghost" size="sm" className="ml-auto" onClick={() => stop.mutate(e.id)} loading={stop.isPending && stop.variables === e.id}>
                  <Square className="size-3" />
                  Parar
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      {canEmail && can(Permission.LEAD_UPDATE) && options.length > 0 && (
        <div className="mt-3 flex gap-2">
          <Select
            aria-label="Sequência"
            value={sequenceId}
            onChange={(e) => setSequenceId(e.target.value)}
            options={[{ value: '', label: 'Inscrever em uma sequência…' }, ...options.map((s) => ({ value: s.id, label: s.name }))]}
          />
          <Button variant="secondary" disabled={!sequenceId} loading={enroll.isPending} onClick={() => enroll.mutate(sequenceId, { onSuccess: () => setSequenceId('') })}>
            Inscrever
          </Button>
        </div>
      )}
    </section>
  )
}
