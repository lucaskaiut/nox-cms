import { useEffect, useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, Plus, Send, Trash2 } from 'lucide-react'
import { Badge, Button, ButtonLink, Card, CardContent, CardHeader, Field, Input, Loading, Page, PageContent, PageHeader, Select, Switch, Textarea } from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import { postsService } from '@/modules/posts/services/posts.service'
import { useTagsQuery } from '@/modules/crm/hooks/useCrm'
import { useSaveSequence, useSequenceEnrollmentsQuery, useSequenceQuery, useTestStep } from '../hooks/useNurture'
import { TRIGGER_LABELS, type NurtureTrigger } from '../services/nurture.service'

interface StepForm {
  id?: string
  delay_days: number
  subject: string
  body: string
  post_id: string
}

const EMPTY_STEP: StepForm = { delay_days: 0, subject: '', body: 'Olá, {{primeiro_nome}}!\n\n', post_id: '' }

const STATUS_BADGE = { active: 'primary', completed: 'success', stopped: 'neutral' } as const

export default function SequenceEditPage() {
  const { id } = useParams<{ id: string }>()
  const isNew = !id
  const navigate = useNavigate()
  const sequence = useSequenceQuery(id)
  const enrollments = useSequenceEnrollmentsQuery(id)
  const tags = useTagsQuery()
  const posts = useQuery({ queryKey: ['posts', 'published-options'], queryFn: () => postsService.list({ status: 'published', per_page: 100 }) })
  const save = useSaveSequence()
  const test = useTestStep(id ?? '')
  const errors = save.error?.fieldErrors ?? {}

  const [form, setForm] = useState({ name: '', trigger: 'manual' as NurtureTrigger, trigger_tag_id: '', trigger_delay_days: 60, is_active: true })
  const [steps, setSteps] = useState<StepForm[]>([{ ...EMPTY_STEP }])

  useEffect(() => {
    if (!sequence.data) return
    const s = sequence.data
    setForm({ name: s.name, trigger: s.trigger, trigger_tag_id: s.trigger_tag?.id ?? '', trigger_delay_days: s.trigger_delay_days, is_active: s.is_active })
    setSteps((s.steps ?? []).map((step) => ({ id: step.id, delay_days: step.delay_days, subject: step.subject, body: step.body, post_id: step.post?.id ?? '' })))
  }, [sequence.data])

  if (!isNew && sequence.isPending) return <Loading />

  const setStep = (index: number, patch: Partial<StepForm>) => setSteps((list) => list.map((s, i) => (i === index ? { ...s, ...patch } : s)))
  const moveStep = (index: number, dir: -1 | 1) =>
    setSteps((list) => {
      const next = [...list]
      const [item] = next.splice(index, 1)
      next.splice(index + dir, 0, item)
      return next
    })

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate(
      {
        id,
        name: form.name.trim(),
        trigger: form.trigger,
        trigger_tag_id: form.trigger === 'tag' ? form.trigger_tag_id || null : null,
        trigger_delay_days: form.trigger === 'lost' ? Number(form.trigger_delay_days) : 0,
        is_active: form.is_active,
        steps: steps.map((s) => ({ id: s.id, delay_days: Number(s.delay_days), subject: s.subject, body: s.body, post_id: s.post_id || null })),
      },
      { onSuccess: (saved) => isNew && navigate(`/nutricao/${saved.id}`, { replace: true }) },
    )
  }

  let elapsed = 0

  return (
    <Page>
      <PageHeader
        title={isNew ? 'Nova sequência' : form.name || 'Sequência'}
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Nutrição', to: '/nutricao' }, { label: isNew ? 'Nova' : 'Editar' }]}
      />
      <PageContent>
        <form onSubmit={submit} className="space-y-5">
          <Card>
            <CardContent className="grid gap-4 sm:grid-cols-2">
              <Field label="Nome" htmlFor="seq-name" required error={errors.name?.[0]}>
                <Input id="seq-name" value={form.name} maxLength={120} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} placeholder="Reativação de perdidos" required />
              </Field>
              <Field label="Quando começa" htmlFor="seq-trigger" error={errors.trigger?.[0]}>
                <Select
                  id="seq-trigger"
                  value={form.trigger}
                  onChange={(e) => setForm((f) => ({ ...f, trigger: e.target.value as NurtureTrigger }))}
                  options={Object.entries(TRIGGER_LABELS).map(([value, label]) => ({ value, label }))}
                />
              </Field>
              {form.trigger === 'tag' && (
                <Field label="Tag" htmlFor="seq-tag" required error={errors.trigger_tag_id?.[0]} hint="O lead entra na sequência quando recebe esta tag.">
                  <Select
                    id="seq-tag"
                    value={form.trigger_tag_id}
                    onChange={(e) => setForm((f) => ({ ...f, trigger_tag_id: e.target.value }))}
                    options={[{ value: '', label: 'Escolha uma tag' }, ...(tags.data ?? []).map((t) => ({ value: t.id, label: t.name }))]}
                  />
                </Field>
              )}
              {form.trigger === 'lost' && (
                <Field label="Dias depois da perda" htmlFor="seq-delay" hint="Leads perdidos há esse tempo entram automaticamente (verificação diária).">
                  <Input id="seq-delay" type="number" min={0} max={365} value={form.trigger_delay_days} onChange={(e) => setForm((f) => ({ ...f, trigger_delay_days: Number(e.target.value) }))} />
                </Field>
              )}
              {form.trigger === 'manual' && <p className="self-end text-sm text-muted">Inscreva leads pela ficha do lead, no funil.</p>}
              <div className="sm:col-span-2">
                <Switch checked={form.is_active} onCheckedChange={(v) => setForm((f) => ({ ...f, is_active: v }))} label="Sequência ativa (desativada, os envios ficam pausados)" />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader
              title="E-mails"
              description="Use {{nome}}, {{primeiro_nome}}, {{empresa}} e {{oferta}}. O rodapé leva sua assinatura e o link de descadastro."
            />
            <CardContent className="space-y-4 pt-0">
              {steps.map((step, index) => {
                elapsed += Number(step.delay_days) || 0
                return (
                  <fieldset key={step.id ?? `new-${index}`} className="space-y-3 rounded-lg border border-surface-3 p-4">
                    <legend className="px-1 text-sm font-semibold text-foreground">
                      E-mail {index + 1} <span className="font-normal text-muted">· dia {elapsed}</span>
                    </legend>
                    <div className="grid gap-3 sm:grid-cols-[10rem_1fr]">
                      <Field label={index === 0 ? 'Dias após a inscrição' : 'Dias após o anterior'} htmlFor={`st-delay-${index}`}>
                        <Input id={`st-delay-${index}`} type="number" min={0} max={365} value={step.delay_days} onChange={(e) => setStep(index, { delay_days: Number(e.target.value) })} />
                      </Field>
                      <Field label="Assunto" htmlFor={`st-subject-${index}`} required error={errors[`steps.${index}.subject`]?.[0]}>
                        <Input id={`st-subject-${index}`} maxLength={160} value={step.subject} onChange={(e) => setStep(index, { subject: e.target.value })} required />
                      </Field>
                    </div>
                    <Field label="Mensagem" htmlFor={`st-body-${index}`} required error={errors[`steps.${index}.body`]?.[0]}>
                      <Textarea id={`st-body-${index}`} rows={6} value={step.body} onChange={(e) => setStep(index, { body: e.target.value })} required />
                    </Field>
                    <Field label="Post em destaque (opcional)" htmlFor={`st-post-${index}`} hint="Vai como cartão com link rastreado (utm_source=email).">
                      <Select
                        id={`st-post-${index}`}
                        value={step.post_id}
                        onChange={(e) => setStep(index, { post_id: e.target.value })}
                        options={[{ value: '', label: 'Nenhum' }, ...(posts.data?.data ?? []).map((p) => ({ value: p.id, label: p.title }))]}
                      />
                    </Field>
                    <div className="flex flex-wrap justify-end gap-1">
                      {!isNew && step.id && (
                        <Button variant="ghost" size="sm" onClick={() => test.mutate(index)} loading={test.isPending && test.variables === index}>
                          <Send className="size-3.5" />
                          Enviar teste para mim
                        </Button>
                      )}
                      <Button variant="ghost" size="sm" aria-label="Mover para cima" disabled={index === 0} onClick={() => moveStep(index, -1)}>
                        <ArrowUp className="size-4" />
                      </Button>
                      <Button variant="ghost" size="sm" aria-label="Mover para baixo" disabled={index === steps.length - 1} onClick={() => moveStep(index, 1)}>
                        <ArrowDown className="size-4" />
                      </Button>
                      <Button variant="ghost" size="sm" aria-label="Remover e-mail" className="text-danger hover:bg-danger-soft hover:text-danger" disabled={steps.length === 1} onClick={() => setSteps((l) => l.filter((_, i) => i !== index))}>
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </fieldset>
                )
              })}
              <Button variant="secondary" onClick={() => setSteps((l) => [...l, { ...EMPTY_STEP, delay_days: 3 }])} disabled={steps.length >= 20}>
                <Plus className="size-4" />
                Adicionar e-mail
              </Button>
            </CardContent>
          </Card>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/nutricao" variant="secondary">
              Voltar
            </ButtonLink>
            <Button type="submit" loading={save.isPending}>
              {isNew ? 'Criar sequência' : 'Salvar'}
            </Button>
          </div>
        </form>

        {!isNew && (
          <Card>
            <CardHeader title="Leads nesta sequência" />
            {(enrollments.data ?? []).length === 0 ? (
              <CardContent className="pt-0 text-sm text-muted">Ninguém inscrito ainda.</CardContent>
            ) : (
              <ul className="divide-y divide-surface-2">
                {(enrollments.data ?? []).map((e) => (
                  <li key={e.id} className="flex flex-wrap items-center gap-2 px-5 py-2.5 text-sm">
                    <span className="font-medium text-foreground">{e.lead?.name || e.lead?.ref}</span>
                    <span className="text-muted">{e.lead?.email}</span>
                    <Badge variant={STATUS_BADGE[e.status]}>{e.status === 'active' ? 'Ativa' : e.status === 'completed' ? 'Concluída' : 'Interrompida'}</Badge>
                    <span className="ml-auto text-xs text-muted">
                      {e.status === 'active' && e.next_run_at ? `próximo: ${formatDateTime(e.next_run_at)}` : e.stopped_reason ?? ''}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        )}
      </PageContent>
    </Page>
  )
}
