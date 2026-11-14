import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { AlertTriangle, CheckCircle2, PlugZap, RefreshCw, RotateCcw, Unplug } from 'lucide-react'
import { Alert, Badge, Button, Card, CardContent, CardHeader, DataTable, Field, Input, Loading, Page, PageContent, PageHeader, Select, Textarea, type Column } from '@/shared/design-system'
import { formatCurrency, formatDateTime, formatRelative } from '@/shared/utils/format'
import {
  useConversionActions,
  useConversionUploads,
  useGoogleAdsQuery,
  useRetryUploads,
  useSaveGoogleAds,
  useSaveSearchConsole,
  useSearchConsoleQuery,
  useSyncSearchConsole,
  useToggleGoogleAds,
  useToggleSearchConsole,
} from '../hooks/useIntegrations'
import type { ConversionUpload } from '../services/integrations.service'

function StatusBadge({ active }: { active: boolean }) {
  return active ? <Badge variant="success">Conectado</Badge> : <Badge variant="neutral">Desconectado</Badge>
}

function ErrorNote({ message }: { message: string | null }) {
  if (!message) return null

  return (
    <p className="flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">
      <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      {message}
    </p>
  )
}

function GoogleAdsCard() {
  const account = useGoogleAdsQuery()
  const save = useSaveGoogleAds()
  const toggle = useToggleGoogleAds()
  const actions = useConversionActions(Boolean(account.data?.has_refresh_token && account.data?.customer_id))
  const [form, setForm] = useState({
    customer_id: '',
    login_customer_id: '',
    developer_token: '',
    client_id: '',
    client_secret: '',
    refresh_token: '',
    qualified_action_id: '',
    won_action_id: '',
    currency: 'BRL',
  })
  const errors = { ...(save.error?.fieldErrors ?? {}), ...(toggle.error?.fieldErrors ?? {}) }

  useEffect(() => {
    if (!account.data) return
    const a = account.data
    setForm((f) => ({
      ...f,
      customer_id: a.customer_id ?? '',
      login_customer_id: a.login_customer_id ?? '',
      client_id: a.client_id ?? '',
      qualified_action_id: a.qualified_action_id ?? '',
      won_action_id: a.won_action_id ?? '',
      currency: a.currency,
      developer_token: '',
      client_secret: '',
      refresh_token: '',
    }))
  }, [account.data])

  if (account.isPending || !account.data) return <Loading />
  const a = account.data
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm((f) => ({ ...f, [key]: e.target.value }))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate({
      customer_id: form.customer_id.trim() || null,
      login_customer_id: form.login_customer_id.trim() || null,
      client_id: form.client_id.trim() || null,
      qualified_action_id: form.qualified_action_id.trim() || null,
      won_action_id: form.won_action_id.trim() || null,
      currency: form.currency.trim() || 'BRL',
      developer_token: form.developer_token.trim() || undefined,
      client_secret: form.client_secret.trim() || undefined,
      refresh_token: form.refresh_token.trim() || undefined,
    })
  }

  const actionField = (key: 'qualified_action_id' | 'won_action_id', label: string, hint: string) => (
    <Field label={label} htmlFor={`ga-${key}`} hint={hint} error={errors[key]?.[0]}>
      {actions.data && actions.data.length > 0 ? (
        <Select
          id={`ga-${key}`}
          value={form[key]}
          onChange={set(key)}
          options={[{ value: '', label: 'Não enviar' }, ...actions.data.map((x) => ({ value: x.id, label: `${x.name} (${x.id})` }))]}
        />
      ) : (
        <Input id={`ga-${key}`} inputMode="numeric" value={form[key]} onChange={set(key)} placeholder="ID numérico da ação" />
      )}
    </Field>
  )

  return (
    <Card>
      <CardHeader
        title="Google Ads: conversões offline automáticas"
        description="Quando um lead que veio de anúncio é qualificado ou fecha, a conversão vai sozinha para o Google Ads, que passa a otimizar os lances para vendas reais."
        actions={<StatusBadge active={a.is_active} />}
      />
      <CardContent className="space-y-5 pt-0">
        <div className="flex flex-wrap gap-2 text-sm">
          <Badge variant="warning">{a.counts.pending} na fila</Badge>
          <Badge variant="success">{a.counts.uploaded} enviadas</Badge>
          <Badge variant={a.counts.failed ? 'danger' : 'neutral'}>{a.counts.failed} com erro</Badge>
          <span className="text-muted">
            Último envio: {a.last_upload_at ? formatRelative(a.last_upload_at) : 'nenhum'} · API {a.api_version}
          </span>
        </div>
        <ErrorNote message={a.last_error} />

        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
          <Field label="ID da conta (cliente)" htmlFor="ga-customer" required hint="Ex.: 123-456-7890" error={errors.customer_id?.[0]}>
            <Input id="ga-customer" value={form.customer_id} onChange={set('customer_id')} />
          </Field>
          <Field label="ID da conta gerenciadora (MCC)" htmlFor="ga-login" hint="Só se o acesso for por uma MCC" error={errors.login_customer_id?.[0]}>
            <Input id="ga-login" value={form.login_customer_id} onChange={set('login_customer_id')} />
          </Field>
          <Field label="Developer token" htmlFor="ga-dev" error={errors.developer_token?.[0]} hint={a.has_developer_token ? 'Já salvo. Deixe em branco para manter.' : 'Centro de API da conta MCC'}>
            <Input id="ga-dev" type="password" autoComplete="off" value={form.developer_token} onChange={set('developer_token')} placeholder={a.has_developer_token ? '••••••••' : ''} />
          </Field>
          <Field label="OAuth client ID" htmlFor="ga-client" error={errors.client_id?.[0]}>
            <Input id="ga-client" value={form.client_id} onChange={set('client_id')} placeholder="….apps.googleusercontent.com" />
          </Field>
          <Field label="OAuth client secret" htmlFor="ga-secret" hint={a.has_client_secret ? 'Já salvo. Deixe em branco para manter.' : undefined}>
            <Input id="ga-secret" type="password" autoComplete="off" value={form.client_secret} onChange={set('client_secret')} placeholder={a.has_client_secret ? '••••••••' : ''} />
          </Field>
          <Field label="Refresh token" htmlFor="ga-refresh" hint={a.has_refresh_token ? 'Já salvo. Deixe em branco para manter.' : 'Escopo https://www.googleapis.com/auth/adwords'}>
            <Input id="ga-refresh" type="password" autoComplete="off" value={form.refresh_token} onChange={set('refresh_token')} placeholder={a.has_refresh_token ? '••••••••' : '1//…'} />
          </Field>
          {actionField('qualified_action_id', 'Ação: lead qualificado', 'Ação do tipo "Importação > Cliques"')}
          {actionField('won_action_id', 'Ação: venda fechada', 'Leva o valor do negócio')}
          <Field label="Moeda" htmlFor="ga-currency">
            <Input id="ga-currency" maxLength={3} value={form.currency} onChange={set('currency')} />
          </Field>
          <div className="flex flex-wrap items-end justify-end gap-2 sm:col-span-2">
            <Button type="submit" variant="secondary" loading={save.isPending}>
              Salvar
            </Button>
            <Button onClick={() => toggle.mutate(true)} loading={toggle.isPending && toggle.variables === true} disabled={!a.is_configured}>
              <PlugZap className="size-4" />
              Testar e ativar
            </Button>
            {a.is_active && (
              <Button variant="secondary" onClick={() => toggle.mutate(false)} loading={toggle.isPending && toggle.variables === false}>
                <Unplug className="size-4" />
                Desativar
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function UploadsCard() {
  const [status, setStatus] = useState('')
  const uploads = useConversionUploads(status)
  const retry = useRetryUploads()

  const columns: Array<Column<ConversionUpload>> = [
    {
      key: 'lead',
      header: 'Lead',
      render: (u) => (u.lead ? <Link to={`/crm?lead=${u.lead.id}`} className="underline">{u.lead.name || u.lead.ref}</Link> : '—'),
    },
    { key: 'kind', header: 'Conversão', render: (u) => (u.kind === 'won' ? 'Venda' : 'Qualificado') },
    { key: 'value', header: 'Valor', render: (u) => formatCurrency(u.value) },
    { key: 'at', header: 'Data', render: (u) => <span className="text-muted">{formatDateTime(u.conversion_at)}</span> },
    {
      key: 'status',
      header: 'Situação',
      render: (u) => (
        <span className="block max-w-xs">
          <Badge variant={u.status === 'uploaded' ? 'success' : u.status === 'failed' ? 'danger' : 'warning'}>
            {u.status === 'uploaded' ? 'Enviada' : u.status === 'failed' ? 'Erro' : `Na fila (${u.attempts} tentativa(s))`}
          </Badge>
          {u.error && u.status !== 'uploaded' && <span className="mt-1 block truncate text-xs text-muted" title={u.error}>{u.error}</span>}
        </span>
      ),
    },
  ]

  return (
    <Card>
      <CardHeader
        title="Histórico de envios"
        description="Cliques recentes podem levar algumas horas para o Google reconhecer; o sistema tenta de novo a cada hora."
        actions={
          <div className="flex gap-2">
            <Select
              aria-label="Filtrar"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              options={[
                { value: '', label: 'Todos' },
                { value: 'pending', label: 'Na fila' },
                { value: 'uploaded', label: 'Enviadas' },
                { value: 'failed', label: 'Com erro' },
              ]}
              className="w-36"
            />
            <Button variant="secondary" onClick={() => retry.mutate()} loading={retry.isPending}>
              <RotateCcw className="size-4" />
              Reenviar erros
            </Button>
          </div>
        }
      />
      <DataTable caption="Conversões enviadas" columns={columns} rows={uploads.data ?? []} rowKey={(u) => u.id} loading={uploads.isPending} emptyState={<p className="p-6 text-center text-sm text-muted">Nenhuma conversão ainda.</p>} />
    </Card>
  )
}

function SearchConsoleCard() {
  const account = useSearchConsoleQuery()
  const save = useSaveSearchConsole()
  const toggle = useToggleSearchConsole()
  const sync = useSyncSearchConsole()
  const [siteUrl, setSiteUrl] = useState('')
  const [json, setJson] = useState('')
  const errors = { ...(save.error?.fieldErrors ?? {}), ...(toggle.error?.fieldErrors ?? {}) }

  useEffect(() => {
    if (account.data) setSiteUrl(account.data.site_url ?? '')
  }, [account.data])

  if (account.isPending || !account.data) return <Loading />
  const a = account.data

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate({ site_url: siteUrl.trim() || null, service_account_json: json.trim() || undefined }, { onSuccess: () => setJson('') })
  }

  return (
    <Card>
      <CardHeader
        title="Google Search Console"
        description="Importa cliques, impressões e posição de cada post todos os dias, para cruzar com os leads no relatório de conteúdo."
        actions={<StatusBadge active={a.is_active} />}
      />
      <CardContent className="space-y-4 pt-0">
        {a.is_active && (
          <p className="flex items-center gap-2 text-sm text-muted">
            <CheckCircle2 className="size-4 text-success" aria-hidden="true" />
            Última importação: {a.last_sync_at ? `${formatRelative(a.last_sync_at)} (${formatDateTime(a.last_sync_at)})` : 'em andamento'}
          </p>
        )}
        <ErrorNote message={a.last_error} />
        <Alert variant="info" title="Como conectar">
          No Google Cloud, crie uma conta de serviço e gere uma chave JSON. Depois, no Search Console, adicione o e-mail dela como usuário (permissão
          restrita basta) da propriedade.
        </Alert>
        <form onSubmit={submit} className="space-y-4">
          <Field label="Propriedade" htmlFor="sc-site" required hint="Como aparece no Search Console: https://seusite.com.br/ ou sc-domain:seusite.com.br" error={errors.site_url?.[0]}>
            <Input id="sc-site" value={siteUrl} onChange={(e) => setSiteUrl(e.target.value)} placeholder="sc-domain:seusite.com.br" />
          </Field>
          <Field
            label="Chave JSON da conta de serviço"
            htmlFor="sc-json"
            error={errors.service_account_json?.[0]}
            hint={a.has_credentials ? `Já salva (${a.service_account_email}). Cole outra só se quiser trocar.` : 'Fica criptografada e nunca é exibida de volta.'}
          >
            <Textarea id="sc-json" rows={4} value={json} onChange={(e) => setJson(e.target.value)} className="font-mono text-xs" placeholder='{"type": "service_account", ...}' />
          </Field>
          <div className="flex flex-wrap justify-end gap-2">
            <Button type="submit" variant="secondary" loading={save.isPending}>
              Salvar
            </Button>
            {a.is_active ? (
              <>
                <Button variant="secondary" onClick={() => sync.mutate()} loading={sync.isPending}>
                  <RefreshCw className="size-4" />
                  Sincronizar agora
                </Button>
                <Button variant="secondary" onClick={() => toggle.mutate(false)} loading={toggle.isPending && toggle.variables === false}>
                  <Unplug className="size-4" />
                  Desativar
                </Button>
              </>
            ) : (
              <Button onClick={() => toggle.mutate(true)} loading={toggle.isPending} disabled={!a.has_credentials || !a.site_url}>
                <PlugZap className="size-4" />
                Testar e ativar
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

export default function IntegrationsPage() {
  return (
    <Page>
      <PageHeader
        title="Integrações"
        description="Conecte o Google para fechar o ciclo anúncio → lead → venda e saber quais posts trazem clientes."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Integrações' }]}
      />
      <PageContent>
        <GoogleAdsCard />
        <UploadsCard />
        <SearchConsoleCard />
      </PageContent>
    </Page>
  )
}
