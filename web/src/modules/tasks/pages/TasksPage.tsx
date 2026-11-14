import { useState } from 'react'
import { Link } from 'react-router'
import { AlarmClock, CalendarClock, CheckCircle2, Hourglass } from 'lucide-react'
import { Badge, Card, CardContent, CardHeader, Checkbox, EmptyState, Loading, Page, PageContent, PageHeader, Select } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { cn } from '@/shared/utils/cn'
import { formatDateTime, formatRelative } from '@/shared/utils/format'
import { stageColor } from '@/modules/crm/constants'
import { useStaleLeadsQuery, useTasksQuery, useUpdateTask } from '@/modules/crm/hooks/useCrm'
import type { LeadTask } from '@/modules/crm/services/crm.service'

function startOfDay(offset = 0) {
  const d = new Date()
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() + offset)
  return d
}

function group(tasks: LeadTask[]) {
  const now = Date.now()
  const tomorrow = startOfDay(1).getTime()

  return {
    overdue: tasks.filter((t) => new Date(t.due_at).getTime() < now),
    today: tasks.filter((t) => {
      const due = new Date(t.due_at).getTime()
      return due >= now && due < tomorrow
    }),
    upcoming: tasks.filter((t) => new Date(t.due_at).getTime() >= tomorrow),
  }
}

function TaskRow({ task, editable }: { task: LeadTask; editable: boolean }) {
  const update = useUpdateTask()
  const color = task.lead?.stage ? stageColor(task.lead.stage.color) : null

  return (
    <li className="flex items-start gap-3 px-5 py-3">
      <Checkbox
        id={`t-${task.id}`}
        aria-label={`Concluir ${task.title}`}
        checked={false}
        disabled={!editable || update.isPending}
        onChange={() => update.mutate({ id: task.id, completed: true })}
      />
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-foreground">{task.title}</p>
        <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted">
          <span className={cn('flex items-center gap-1', task.overdue && 'font-medium text-danger')}>
            <CalendarClock className="size-3" aria-hidden="true" />
            {formatDateTime(task.due_at)}
          </span>
          {task.lead && (
            <Link to={`/crm?lead=${task.lead.id}`} className="underline">
              {task.lead.name || task.lead.ref}
              {task.lead.company ? ` · ${task.lead.company}` : ''}
            </Link>
          )}
          {task.lead?.phone && <span>{task.lead.phone}</span>}
          {color && task.lead?.stage && (
            <span className={cn('rounded px-1.5 py-0.5', color.soft)}>{task.lead.stage.name}</span>
          )}
          {task.assignee && <span>· {task.assignee.name}</span>}
        </p>
        {task.notes && <p className="mt-1 text-xs whitespace-pre-line text-muted">{task.notes}</p>}
      </div>
    </li>
  )
}

function TaskGroup({ title, icon: Icon, tasks, tone, editable }: { title: string; icon: typeof AlarmClock; tasks: LeadTask[]; tone: string; editable: boolean }) {
  if (tasks.length === 0) return null

  return (
    <Card>
      <CardHeader
        title={
          <span className="flex items-center gap-2">
            <Icon className={cn('size-4', tone)} aria-hidden="true" />
            {title}
          </span>
        }
        actions={<Badge variant="neutral">{tasks.length}</Badge>}
      />
      <ul className="divide-y divide-surface-2">
        {tasks.map((task) => (
          <TaskRow key={task.id} task={task} editable={editable} />
        ))}
      </ul>
    </Card>
  )
}

/** Agenda comercial: tarefas de follow-up e leads esquecidos. */
export default function TasksPage() {
  const { can } = usePermissions()
  const [scope, setScope] = useState<'mine' | 'all'>('mine')
  const tasks = useTasksQuery({ scope, status: 'open' })
  const stale = useStaleLeadsQuery()
  const groups = group(tasks.data ?? [])
  const editable = can(Permission.LEAD_UPDATE)

  return (
    <Page>
      <PageHeader
        title="Tarefas"
        description="Próximos passos combinados com os leads. Você recebe um lembrete por e-mail quando a tarefa vence."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Tarefas' }]}
        actions={
          <Select
            aria-label="Mostrar tarefas"
            value={scope}
            onChange={(e) => setScope(e.target.value as 'mine' | 'all')}
            options={[
              { value: 'mine', label: 'Minhas tarefas' },
              { value: 'all', label: 'Toda a equipe' },
            ]}
            className="w-48"
          />
        }
      />

      <PageContent className="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
        <div className="space-y-5">
          {tasks.isPending ? (
            <Loading />
          ) : (tasks.data ?? []).length === 0 ? (
            <Card>
              <EmptyState icon={CheckCircle2} title="Tudo em dia" description="Nenhuma tarefa aberta. Crie tarefas na ficha do lead, no funil." />
            </Card>
          ) : (
            <>
              <TaskGroup title="Atrasadas" icon={AlarmClock} tasks={groups.overdue} tone="text-danger" editable={editable} />
              <TaskGroup title="Hoje" icon={CalendarClock} tasks={groups.today} tone="text-primary" editable={editable} />
              <TaskGroup title="Próximas" icon={CalendarClock} tasks={groups.upcoming} tone="text-muted" editable={editable} />
            </>
          )}
        </div>

        <Card>
          <CardHeader
            title={
              <span className="flex items-center gap-2">
                <Hourglass className="size-4 text-warning" aria-hidden="true" />
                Leads parados
              </span>
            }
            description="Em aberto, sem atividade recente e sem tarefa futura."
          />
          {stale.isPending ? (
            <CardContent>
              <Loading />
            </CardContent>
          ) : (stale.data ?? []).length === 0 ? (
            <CardContent className="text-sm text-muted">Nenhum lead esquecido. 👏</CardContent>
          ) : (
            <ul className="divide-y divide-surface-2">
              {(stale.data ?? []).slice(0, 30).map((lead) => (
                <li key={lead.id}>
                  <Link to={`/crm?lead=${lead.id}`} className="block px-5 py-3 transition-colors hover:bg-surface-2/60">
                    <span className="block truncate text-sm font-medium text-foreground">{lead.name || lead.ref}</span>
                    <span className="block text-xs text-muted">
                      {lead.stage?.name} · última atividade {formatRelative(lead.last_activity_at ?? lead.created_at)}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </PageContent>
    </Page>
  )
}
