import { useEffect, useState, type FormEvent } from 'react'
import { Send, Trash2, X } from 'lucide-react'
import { Button, Card, CardContent, CardHeader, Field, Input, Loading, Page, PageContent, PageHeader, Switch, Textarea } from '@/shared/design-system'
import { stageColor, STAGE_COLORS } from '@/modules/crm/constants'
import { useCrmSettingsQuery, useDeleteTag, useSaveCrmSettings, useTagsQuery, useTestNotification, useUpdateTag } from '@/modules/crm/hooks/useCrm'
import type { CrmSettings } from '@/modules/crm/services/crm.service'

type FormState = Omit<CrmSettings, 'has_telegram_bot_token' | 'template_variables'> & { telegram_bot_token: string; notify_emails_text: string; lost_reasons_text: string }

function toForm(s: CrmSettings): FormState {
  return {
    ...s,
    telegram_bot_token: '',
    notify_emails_text: s.notify_emails.join(', '),
    lost_reasons_text: s.lost_reasons.join('\n'),
  }
}

function TagsCard() {
  const tags = useTagsQuery()
  const update = useUpdateTag()
  const remove = useDeleteTag()

  return (
    <Card>
      <CardHeader title="Tags" description="Criadas na ficha do lead. Aqui você troca a cor ou remove." />
      <CardContent className="pt-0">
        {(tags.data ?? []).length === 0 ? (
          <p className="text-sm text-muted">Nenhuma tag ainda.</p>
        ) : (
          <ul className="space-y-2">
            {(tags.data ?? []).map((tag) => (
              <li key={tag.id} className="flex items-center gap-2 text-sm">
                <span className={`rounded-full px-2 py-0.5 text-xs ${stageColor(tag.color).soft}`}>{tag.name}</span>
                <span className="text-xs text-muted">{tag.leads_count ?? 0} lead(s)</span>
                <div className="ml-auto flex gap-1" role="radiogroup" aria-label={`Cor de ${tag.name}`}>
                  {Object.entries(STAGE_COLORS).map(([key, c]) => (
                    <button
                      key={key}
                      type="button"
                      role="radio"
                      aria-checked={tag.color === key}
                      aria-label={c.label}
                      onClick={() => update.mutate({ id: tag.id, color: key })}
                      className={`size-4 rounded-full ${c.dot} ${tag.color === key ? 'ring-2 ring-foreground ring-offset-2 ring-offset-surface' : ''}`}
                    />
                  ))}
                </div>
                <button type="button" aria-label={`Remover ${tag.name}`} onClick={() => remove.mutate(tag.id)} className="rounded p-1 text-subtle hover:bg-danger-soft hover:text-danger">
                  <Trash2 className="size-3.5" />
                </button>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

/** Site, motivos de perda, alertas, resposta automática e tags. */
export default function CrmSettingsPage() {
  const settings = useCrmSettingsQuery()
  const save = useSaveCrmSettings()
  const test = useTestNotification()
  const [form, setForm] = useState<FormState | null>(null)
  const errors = { ...(save.error?.fieldErrors ?? {}), ...(test.error?.fieldErrors ?? {}) }

  useEffect(() => {
    if (settings.data) setForm(toForm(settings.data))
  }, [settings.data])

  if (!form || !settings.data) return <Loading />
  const data = settings.data
  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => (f ? { ...f, [key]: value } : f))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate({
      site_url: form.site_url?.trim() || null,
      post_url_pattern: form.post_url_pattern?.trim() || null,
      lost_reasons: form.lost_reasons_text.split('\n').map((r) => r.trim()).filter(Boolean),
      stale_lead_days: Number(form.stale_lead_days),
      stale_post_months: Number(form.stale_post_months),
      notify_emails: form.notify_emails_text.split(/[,;\s]+/).map((e) => e.trim()).filter(Boolean),
      notify_new_lead: form.notify_new_lead,
      notify_daily_digest: form.notify_daily_digest,
      telegram_bot_token: form.telegram_bot_token.trim() || undefined,
      telegram_chat_id: form.telegram_chat_id?.trim() || null,
      auto_reply_enabled: form.auto_reply_enabled,
      auto_reply_subject: form.auto_reply_subject?.trim() || null,
      auto_reply_body: form.auto_reply_body?.trim() || null,
      email_signature: form.email_signature?.trim() || null,
    })
  }

  const err = (key: string) => errors[key]?.[0] ?? Object.entries(errors).find(([k]) => k.startsWith(`${key}.`))?.[1]?.[0]

  return (
    <Page>
      <PageHeader
        title="Configurações do CRM"
        description="Como o CRM reconhece seus posts, quando avisa você e o que responde aos leads."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Configurações do CRM' }]}
      />
      <PageContent className="grid gap-6 lg:grid-cols-[1fr_22rem] lg:items-start">
        <form onSubmit={submit} className="space-y-6">
          <Card>
            <CardHeader title="Site e blog" description="Usado para saber de qual post veio cada lead e para montar os links dos e-mails." />
            <CardContent className="grid gap-4 pt-0 sm:grid-cols-2">
              <Field label="Endereço do site" htmlFor="crm-site" error={err('site_url')}>
                <Input id="crm-site" type="url" value={form.site_url ?? ''} onChange={(e) => set('site_url', e.target.value)} placeholder="https://seusite.com.br" />
              </Field>
              <Field label="Caminho dos posts" htmlFor="crm-pattern" error={err('post_url_pattern')} hint="Use {slug}. Sem isso, vale o último trecho da URL.">
                <Input id="crm-pattern" value={form.post_url_pattern ?? ''} onChange={(e) => set('post_url_pattern', e.target.value)} placeholder="/blog/{slug}" />
              </Field>
              <Field label="Lead parado após (dias)" htmlFor="crm-stale" error={err('stale_lead_days')}>
                <Input id="crm-stale" type="number" min={1} max={90} value={form.stale_lead_days} onChange={(e) => set('stale_lead_days', Number(e.target.value))} />
              </Field>
              <Field label="Post para atualizar após (meses)" htmlFor="crm-stale-post" error={err('stale_post_months')}>
                <Input id="crm-stale-post" type="number" min={1} max={36} value={form.stale_post_months} onChange={(e) => set('stale_post_months', Number(e.target.value))} />
              </Field>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Motivos de perda" description="Opções exibidas ao marcar um lead como perdido. Um por linha." />
            <CardContent className="pt-0">
              <Textarea aria-label="Motivos de perda" rows={6} value={form.lost_reasons_text} onChange={(e) => set('lost_reasons_text', e.target.value)} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Alertas" description="Responder rápido aumenta muito a chance de fechar." />
            <CardContent className="space-y-4 pt-0">
              <Switch checked={form.notify_new_lead} onCheckedChange={(v) => set('notify_new_lead', v)} label="Avisar a cada lead novo do site" />
              <Switch checked={form.notify_daily_digest} onCheckedChange={(v) => set('notify_daily_digest', v)} label="Resumo diário às 8h (tarefas, leads parados)" />
              <Field label="E-mails que recebem os alertas" htmlFor="crm-emails" error={err('notify_emails')} hint="Separe por vírgula. Vazio = todos os usuários que veem leads.">
                <Input id="crm-emails" value={form.notify_emails_text} onChange={(e) => set('notify_emails_text', e.target.value)} placeholder="voce@empresa.com" />
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field
                  label="Token do bot do Telegram"
                  htmlFor="crm-tg-token"
                  error={err('telegram_bot_token')}
                  hint={data.has_telegram_bot_token ? 'Já salvo. Deixe em branco para manter.' : 'Crie um bot com o @BotFather'}
                >
                  <div className="flex gap-2">
                    <Input id="crm-tg-token" type="password" autoComplete="off" value={form.telegram_bot_token} onChange={(e) => set('telegram_bot_token', e.target.value)} placeholder={data.has_telegram_bot_token ? '••••••••' : '123456:ABC…'} />
                    {data.has_telegram_bot_token && (
                      <Button variant="ghost" aria-label="Remover token do Telegram" onClick={() => save.mutate({ remove_telegram_bot_token: true })}>
                        <X className="size-4" />
                      </Button>
                    )}
                  </div>
                </Field>
                <Field label="Chat ID do Telegram" htmlFor="crm-tg-chat" error={err('telegram_chat_id')} hint="Seu ID ou o de um grupo com o bot">
                  <Input id="crm-tg-chat" value={form.telegram_chat_id ?? ''} onChange={(e) => set('telegram_chat_id', e.target.value)} />
                </Field>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Resposta automática por e-mail" description="Enviada a quem preenche o formulário do site e informa e-mail." />
            <CardContent className="space-y-4 pt-0">
              <Switch checked={form.auto_reply_enabled} onCheckedChange={(v) => set('auto_reply_enabled', v)} label="Enviar resposta automática" />
              <Field label="Assunto" htmlFor="crm-ar-subject" error={err('auto_reply_subject')}>
                <Input id="crm-ar-subject" value={form.auto_reply_subject ?? ''} onChange={(e) => set('auto_reply_subject', e.target.value)} placeholder="Recebemos sua mensagem, {{primeiro_nome}}" />
              </Field>
              <Field label="Mensagem" htmlFor="crm-ar-body" error={err('auto_reply_body')} hint={`Variáveis: ${data.template_variables.map((v) => `{{${v}}}`).join(', ')}`}>
                <Textarea
                  id="crm-ar-body"
                  rows={6}
                  value={form.auto_reply_body ?? ''}
                  onChange={(e) => set('auto_reply_body', e.target.value)}
                  placeholder={'Olá, {{primeiro_nome}}!\n\nRecebemos seu contato e vamos responder em até 1 dia útil.'}
                />
              </Field>
              <Field label="Assinatura (todos os e-mails para leads)" htmlFor="crm-sign" error={err('email_signature')}>
                <Textarea id="crm-sign" rows={3} value={form.email_signature ?? ''} onChange={(e) => set('email_signature', e.target.value)} placeholder={'Lucas\nNox · (41) 99999-9999'} />
              </Field>
            </CardContent>
          </Card>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="secondary" onClick={() => test.mutate()} loading={test.isPending}>
              <Send className="size-4" />
              Enviar alerta de teste
            </Button>
            <Button type="submit" loading={save.isPending}>
              Salvar configurações
            </Button>
          </div>
        </form>

        <TagsCard />
      </PageContent>
    </Page>
  )
}
