import { useState, type FormEvent } from 'react'
import { CalendarClock, Plus, Trash2 } from 'lucide-react'
import { Button, Checkbox, Field, Input, Select, Spinner } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { cn } from '@/shared/utils/cn'
import { formatDateTime, toDateTimeInput } from '@/shared/utils/format'
import { useAssigneesQuery, useCreateTask, useDeleteTask, useTasksQuery, useUpdateTask } from '../hooks/useCrm'

const QUICK = [
  { label: 'Hoje 17h', at: () => withHour(new Date(), 17) },
  { label: 'Amanhã 9h', at: () => withHour(addDays(new Date(), 1), 9) },
  { label: 'Em 3 dias', at: () => withHour(addDays(new Date(), 3), 9) },
  { label: 'Próxima semana', at: () => withHour(addDays(new Date(), 7), 9) },
]

function addDays(date: Date, days: number) {
  const copy = new Date(date)
  copy.setDate(copy.getDate() + days)
  return copy
}

function withHour(date: Date, hour: number) {
  const copy = new Date(date)
  copy.setHours(hour, 0, 0, 0)
  return copy
}

/** Próximos passos combinados com o lead. */
export function LeadTasksSection({ leadId }: { leadId: string }) {
  const { can } = usePermissions()
  const me = useSessionStore((state) => state.user)
  const tasks = useTasksQuery({ scope: 'all', status: 'all', lead: leadId })
  const assignees = useAssigneesQuery()
  const create = useCreateTask(leadId)
  const update = useUpdateTask()
  const remove = useDeleteTask()
  const [adding, setAdding] = useState(false)
  const [form, setForm] = useState({ title: '', due_at: toDateTimeInput(withHour(addDays(new Date(), 1), 9)), assigned_to: me?.id ?? '' })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    if (!form.title.trim() || !form.due_at) return
    create.mutate(
      { title: form.title.trim(), due_at: new Date(form.due_at).toISOString(), assigned_to: form.assigned_to || null },
      {
        onSuccess: () => {
          setAdding(false)
          setForm((current) => ({ ...current, title: '' }))
        },
      },
    )
  }

  const editable = can(Permission.LEAD_UPDATE)
  const open = (tasks.data ?? []).filter((t) => !t.completed_at)
  const done = (tasks.data ?? []).filter((t) => t.completed_at).slice(0, 3)

  return (
    <section aria-labelledby="lead-tasks">
      <div className="flex items-center justify-between gap-2">
        <h3 id="lead-tasks" className="text-sm font-semibold text-foreground">
          Próximos passos
        </h3>
        <Can permission={Permission.LEAD_UPDATE}>
          {!adding && (
            <Button variant="ghost" size="sm" onClick={() => setAdding(true)}>
              <Plus className="size-3.5" />
              Tarefa
            </Button>
          )}
        </Can>
      </div>

      {adding && (
        <form onSubmit={submit} className="mt-3 space-y-3 rounded-lg border border-surface-3 p-3">
          <Field label="O que fazer" htmlFor="task-title" required>
            <Input id="task-title" autoFocus maxLength={160} value={form.title} onChange={(e) => setForm((c) => ({ ...c, title: e.target.value }))} placeholder="Ligar para apresentar a proposta" />
          </Field>
          <div className="flex flex-wrap gap-1.5">
            {QUICK.map((q) => (
              <button
                key={q.label}
                type="button"
                className="rounded-full border border-surface-3 px-2.5 py-1 text-xs text-muted hover:bg-surface-2 hover:text-foreground"
                onClick={() => setForm((c) => ({ ...c, due_at: toDateTimeInput(q.at()) }))}
              >
                {q.label}
              </button>
            ))}
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Prazo" htmlFor="task-due" required>
              <Input id="task-due" type="datetime-local" value={form.due_at} onChange={(e) => setForm((c) => ({ ...c, due_at: e.target.value }))} />
            </Field>
            <Field label="Responsável" htmlFor="task-assignee">
              <Select
                id="task-assignee"
                value={form.assigned_to}
                onChange={(e) => setForm((c) => ({ ...c, assigned_to: e.target.value }))}
                options={[{ value: '', label: 'Eu mesmo' }, ...(assignees.data ?? []).map((u) => ({ value: u.id, label: u.name }))]}
              />
            </Field>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="secondary" size="sm" onClick={() => setAdding(false)}>
              Cancelar
            </Button>
            <Button type="submit" size="sm" loading={create.isPending} disabled={!form.title.trim()}>
              Criar tarefa
            </Button>
          </div>
        </form>
      )}

      {tasks.isPending ? (
        <div className="mt-3 flex justify-center">
          <Spinner />
        </div>
      ) : open.length === 0 && !adding ? (
        <p className="mt-2 text-sm text-muted">Nenhuma tarefa aberta. Combine o próximo passo para o lead não esfriar.</p>
      ) : (
        <ul className="mt-3 space-y-2">
          {[...open, ...done].map((task) => (
            <li key={task.id} className="flex items-start gap-3 rounded-lg bg-surface-2/60 px-3 py-2">
              <Checkbox
                id={`task-${task.id}`}
                aria-label={`Concluir ${task.title}`}
                checked={Boolean(task.completed_at)}
                disabled={!editable || update.isPending}
                onChange={(e) => update.mutate({ id: task.id, completed: e.target.checked })}
              />
              <div className="min-w-0 flex-1">
                <p className={cn('text-sm text-foreground', task.completed_at && 'text-muted line-through')}>{task.title}</p>
                <p className={cn('flex items-center gap-1 text-xs', task.overdue ? 'font-medium text-danger' : 'text-muted')}>
                  <CalendarClock className="size-3" aria-hidden="true" />
                  {formatDateTime(task.due_at)}
                  {task.overdue && ' · atrasada'}
                  {task.assignee && ` · ${task.assignee.name}`}
                </p>
              </div>
              {editable && (
                <button
                  type="button"
                  aria-label={`Remover ${task.title}`}
                  onClick={() => remove.mutate(task.id)}
                  className="rounded p-1 text-subtle hover:bg-danger-soft hover:text-danger"
                >
                  <Trash2 className="size-3.5" />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
