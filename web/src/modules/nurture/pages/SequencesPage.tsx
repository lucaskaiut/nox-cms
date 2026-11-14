import { useState } from 'react'
import { useNavigate } from 'react-router'
import { MailPlus, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge, Button, ButtonLink, Card, ConfirmDialog, DataTable, EmptyState, Page, PageContent, PageHeader, Switch, type Column } from '@/shared/design-system'
import { useDeleteSequence, useSequencesQuery, useToggleSequence } from '../hooks/useNurture'
import { TRIGGER_LABELS, type NurtureSequence } from '../services/nurture.service'

function triggerText(s: NurtureSequence) {
  if (s.trigger === 'tag') return `Tag “${s.trigger_tag?.name ?? '?'}”`
  if (s.trigger === 'lost') return `${s.trigger_delay_days} dias após a perda`
  return TRIGGER_LABELS.manual
}

/** Sequências de e-mail para nutrir leads que ainda não estão prontos. */
export default function SequencesPage() {
  const sequences = useSequencesQuery()
  const toggle = useToggleSequence()
  const remove = useDeleteSequence()
  const navigate = useNavigate()
  const [deleting, setDeleting] = useState<NurtureSequence | null>(null)

  const columns: Array<Column<NurtureSequence>> = [
    { key: 'name', header: 'Sequência', render: (s) => <span className="font-medium text-foreground">{s.name}</span> },
    { key: 'trigger', header: 'Início', render: (s) => <span className="text-muted">{triggerText(s)}</span> },
    { key: 'steps', header: 'E-mails', className: 'text-right', render: (s) => <span className="tabular-nums">{s.steps_count ?? 0}</span> },
    {
      key: 'enrolled',
      header: 'Leads',
      render: (s) => (
        <span className="flex flex-wrap gap-1.5">
          <Badge variant="primary">{s.active_count ?? 0} ativos</Badge>
          <Badge variant="success">{s.completed_count ?? 0} concluídos</Badge>
        </span>
      ),
    },
    {
      key: 'active',
      header: 'Ativa',
      render: (s) => (
        <Switch checked={s.is_active} label={`Ativar ${s.name}`} disabled={toggle.isPending} onCheckedChange={(checked) => toggle.mutate({ id: s.id, is_active: checked })} />
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (s) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="sm" aria-label={`Editar ${s.name}`} onClick={() => navigate(`/nutricao/${s.id}`)}>
            <Pencil className="size-4" />
          </Button>
          <Button variant="ghost" size="sm" aria-label={`Excluir ${s.name}`} className="text-danger hover:bg-danger-soft hover:text-danger" onClick={() => setDeleting(s)}>
            <Trash2 className="size-4" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Nutrição por e-mail"
        description="Sequências automáticas com seus posts para leads que ainda não estão prontos para comprar. Param sozinhas quando o lead fecha, é perdido ou se descadastra."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Nutrição' }]}
        actions={
          <ButtonLink to="/nutricao/nova">
            <Plus className="size-4" />
            Nova sequência
          </ButtonLink>
        }
      />
      <PageContent>
        <Card>
          <DataTable
            caption="Sequências"
            columns={columns}
            rows={sequences.data ?? []}
            rowKey={(s) => s.id}
            loading={sequences.isPending}
            emptyState={
              <EmptyState
                icon={MailPlus}
                title="Nenhuma sequência"
                description="Comece com uma reativação: 60 dias depois de perder um lead, envie um case e pergunte se o momento mudou."
                action={
                  <ButtonLink to="/nutricao/nova">
                    <Plus className="size-4" />
                    Nova sequência
                  </ButtonLink>
                }
              />
            }
          />
        </Card>
      </PageContent>
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        onConfirm={() => deleting && remove.mutate(deleting.id, { onSettled: () => setDeleting(null) })}
        loading={remove.isPending}
        title="Excluir sequência"
        description={<>Excluir <strong>{deleting?.name}</strong>? Os envios pendentes desta sequência são cancelados.</>}
        confirmLabel="Excluir"
      />
    </Page>
  )
}
