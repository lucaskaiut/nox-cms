import { useEffect, useState, type FormEvent } from 'react'
import { CheckCircle2, Copy, PlugZap, RefreshCw, Unplug } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  Field,
  Input,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'
import { formatDateTime, formatRelative } from '@/shared/utils/format'
import { useSaveWhatsAppAccount, useToggleWhatsApp, useWhatsAppAccount } from '../hooks/useWhatsApp'

function randomToken(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(30))
  return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}

function CopyField({ id, label, value, hint }: { id: string; label: string; value: string; hint?: string }) {
  return (
    <Field label={label} htmlFor={id} hint={hint}>
      <div className="flex gap-2">
        <Input id={id} value={value} readOnly className="font-mono text-xs" onFocus={(e) => e.target.select()} />
        <Button
          variant="secondary"
          aria-label={`Copiar ${label}`}
          onClick={() => {
            void navigator.clipboard.writeText(value)
            toast.success('Copiado')
          }}
        >
          <Copy className="size-4" />
        </Button>
      </div>
    </Field>
  )
}

/** Conexão manual com a WhatsApp Cloud API (sem Embedded Signup). */
export default function WhatsAppSettingsPage() {
  const account = useWhatsAppAccount()
  const save = useSaveWhatsAppAccount()
  const toggle = useToggleWhatsApp()
  const [form, setForm] = useState({ phone_number_id: '', waba_id: '', access_token: '', app_secret: '', verify_token: '', graph_version: 'v23.0' })
  const errors = save.error?.fieldErrors ?? {}

  useEffect(() => {
    if (!account.data) return
    setForm((current) => ({
      ...current,
      phone_number_id: account.data.phone_number_id ?? '',
      waba_id: account.data.waba_id ?? '',
      verify_token: account.data.verify_token,
      graph_version: account.data.graph_version,
      access_token: '',
      app_secret: '',
    }))
  }, [account.data])

  if (account.isPending || !account.data) return <Loading />
  const data = account.data
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm((c) => ({ ...c, [key]: e.target.value }))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate({
      ...form,
      phone_number_id: form.phone_number_id.trim(),
      waba_id: form.waba_id.trim(),
      access_token: form.access_token.trim() || undefined,
      app_secret: form.app_secret.trim() || undefined,
    })
  }

  return (
    <Page>
      <PageHeader
        title="WhatsApp"
        description="Conexão com a API oficial da Meta (Cloud API). As conversas aparecem em Comercial > Conversas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'WhatsApp' }]}
      />

      <PageContent className="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div className="space-y-6">
          <Card>
            <CardHeader
              title="Status"
              actions={data.is_active ? <Badge variant="success">Conectado</Badge> : <Badge variant="neutral">Desconectado</Badge>}
            />
            <CardContent className="space-y-3 text-sm">
              {data.is_active ? (
                <p className="flex items-center gap-2 text-foreground">
                  <CheckCircle2 className="size-4 text-success" aria-hidden="true" />
                  {data.verified_name} · {data.display_phone}
                </p>
              ) : (
                <p className="text-muted">Preencha os dados abaixo, salve e clique em “Testar e ativar”.</p>
              )}
              <p className="text-muted">
                Último evento recebido da Meta:{' '}
                {data.last_webhook_at ? `${formatRelative(data.last_webhook_at)} (${formatDateTime(data.last_webhook_at)})` : 'nenhum ainda'}
              </p>
              <div className="flex flex-wrap gap-2 pt-1">
                <Button onClick={() => toggle.mutate(true)} loading={toggle.isPending && toggle.variables === true}>
                  <PlugZap className="size-4" />
                  Testar e ativar
                </Button>
                {data.is_active && (
                  <Button variant="secondary" onClick={() => toggle.mutate(false)} loading={toggle.isPending && toggle.variables === false}>
                    <Unplug className="size-4" />
                    Desativar
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Credenciais" description="Token e app secret ficam criptografados e nunca são exibidos de volta." />
            <CardContent>
              <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                <Field label="Phone number ID" htmlFor="wa-pnid" required error={errors.phone_number_id?.[0]}>
                  <Input id="wa-pnid" value={form.phone_number_id} onChange={set('phone_number_id')} inputMode="numeric" required />
                </Field>
                <Field label="WhatsApp Business Account ID" htmlFor="wa-waba" required error={errors.waba_id?.[0]}>
                  <Input id="wa-waba" value={form.waba_id} onChange={set('waba_id')} inputMode="numeric" required />
                </Field>
                <Field
                  label="Token de acesso permanente"
                  htmlFor="wa-token"
                  className="sm:col-span-2"
                  error={errors.access_token?.[0]}
                  hint={data.has_access_token ? 'Já existe um token salvo. Deixe em branco para manter.' : 'Token de usuário do sistema (não expira).'}
                >
                  <Input id="wa-token" type="password" autoComplete="off" value={form.access_token} onChange={set('access_token')} placeholder={data.has_access_token ? '••••••••••••' : 'EAAG…'} />
                </Field>
                <Field
                  label="App secret"
                  htmlFor="wa-secret"
                  error={errors.app_secret?.[0]}
                  hint={data.has_app_secret ? 'Já salvo. Deixe em branco para manter.' : 'Valida a assinatura dos webhooks.'}
                >
                  <Input id="wa-secret" type="password" autoComplete="off" value={form.app_secret} onChange={set('app_secret')} placeholder={data.has_app_secret ? '••••••••' : ''} />
                </Field>
                <Field label="Versão da Graph API" htmlFor="wa-version" error={errors.graph_version?.[0]}>
                  <Input id="wa-version" value={form.graph_version} onChange={set('graph_version')} placeholder="v23.0" />
                </Field>
                <div className="sm:col-span-2">
                  <Field label="Token de verificação do webhook" htmlFor="wa-verify" error={errors.verify_token?.[0]} hint="Você mesmo define. Cole o mesmo valor no painel da Meta.">
                    <div className="flex gap-2">
                      <Input id="wa-verify" value={form.verify_token} onChange={set('verify_token')} className="font-mono text-xs" />
                      <Button variant="secondary" onClick={() => setForm((c) => ({ ...c, verify_token: randomToken() }))} aria-label="Gerar novo token de verificação">
                        <RefreshCw className="size-4" />
                      </Button>
                    </div>
                  </Field>
                </div>
                <div className="flex justify-end sm:col-span-2">
                  <Button type="submit" loading={save.isPending}>
                    Salvar
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Webhook" description="Configure em developers.facebook.com > seu app > WhatsApp > Configuração." />
            <CardContent className="space-y-4">
              <CopyField id="wa-webhook-url" label="URL de callback" value={data.webhook_url} />
              <CopyField id="wa-webhook-token" label="Token de verificação" value={data.verify_token} hint="Salve antes de copiar se você acabou de alterar." />
            </CardContent>
          </Card>
        </div>

        <aside>
          <Card>
            <CardHeader title="Passo a passo" />
            <CardContent>
              <ol className="list-decimal space-y-3 pl-5 text-sm text-muted">
                <li>
                  Em <strong className="text-foreground">developers.facebook.com</strong>, crie (ou abra) um app do tipo
                  Empresa e adicione o produto <strong className="text-foreground">WhatsApp</strong>.
                </li>
                <li>
                  Em WhatsApp &gt; Configuração da API, copie o <strong className="text-foreground">Phone number ID</strong> e o{' '}
                  <strong className="text-foreground">WhatsApp Business Account ID</strong>.
                </li>
                <li>
                  No Gerenciador de Negócios &gt; Usuários do sistema, crie um usuário <em>admin</em>, atribua o app e a conta do
                  WhatsApp e gere um token <strong className="text-foreground">sem expiração</strong> com
                  <code className="mx-1 text-xs">whatsapp_business_messaging</code>e
                  <code className="ml-1 text-xs">whatsapp_business_management</code>.
                </li>
                <li>
                  Em Configurações do app &gt; Básico, copie a <strong className="text-foreground">Chave secreta do app</strong>.
                </li>
                <li>Salve aqui. Depois, no webhook do app, cole a URL e o token de verificação e assine o campo <code className="text-xs">messages</code>.</li>
                <li>
                  Clique em <strong className="text-foreground">Testar e ativar</strong>: o painel valida o número e inscreve o app na conta
                  (passo que o Embedded Signup faria sozinho).
                </li>
              </ol>
              <Alert variant="warning" title="Número que já usa o app WhatsApp" className="mt-4">
                Ao registrar um número na Cloud API ele deixa de funcionar no aplicativo WhatsApp Business do celular. Se quiser manter
                o celular, use outro número na API.
              </Alert>
            </CardContent>
          </Card>
        </aside>
      </PageContent>
    </Page>
  )
}
