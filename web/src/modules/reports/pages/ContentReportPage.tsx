import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { ArrowDownRight, ArrowUpRight, RefreshCcw } from 'lucide-react'
import { Badge, Card, CardContent, CardHeader, DataTable, Page, PageContent, PageHeader, Select, type Column } from '@/shared/design-system'
import { formatCurrency, formatDate, formatNumber, formatPercent } from '@/shared/utils/format'
import { BarList } from '../components/Charts'
import { PERIODS, periodParams } from '../period'
import { useContentReport, useStalePosts } from '../hooks/useReports'
import type { ContentGroup, ContentRow, StalePost } from '../services/reports.service'

const SORTS = [
  { value: 'won', label: 'Ordenar por vendas' },
  { value: 'leads', label: 'Ordenar por leads' },
  { value: 'clicks', label: 'Ordenar por cliques' },
  { value: 'lead_rate', label: 'Ordenar por conversão' },
]

const REASON_BADGE: Record<StalePost['reasons'][number]['type'], 'warning' | 'danger' | 'primary' | 'neutral'> = {
  old: 'neutral',
  traffic_drop: 'danger',
  striking_distance: 'primary',
  no_leads: 'warning',
}

function Trend({ current, previous }: { current: number | null; previous: number | null }) {
  if (current === null || previous === null || previous < 5) return null
  const change = ((current - previous) / previous) * 100
  if (Math.abs(change) < 10) return null
  const Icon = change > 0 ? ArrowUpRight : ArrowDownRight

  return (
    <span className={change > 0 ? 'text-success' : 'text-danger'} title={`${previous} no período anterior`}>
      <Icon className="inline size-3.5" aria-label={change > 0 ? 'subiu' : 'caiu'} />
    </span>
  )
}

/** Quais posts trazem clientes (não só visitas) e quais precisam de atenção. */
export default function ContentReportPage() {
  const [days, setDays] = useState('90')
  const [sort, setSort] = useState('won')
  const report = useContentReport(periodParams(days))
  const stale = useStalePosts()
  const hasSearch = (report.data?.posts ?? []).some((p) => p.clicks !== null)

  const rows = useMemo(() => {
    const list = [...(report.data?.posts ?? [])]
    const key = sort as keyof ContentRow
    return list.sort((a, b) => Number(b[key] ?? -1) - Number(a[key] ?? -1) || b.leads - a.leads)
  }, [report.data, sort])

  const columns: Array<Column<ContentRow>> = [
    {
      key: 'title',
      header: 'Post',
      render: (p) => (
        <div className="min-w-0 max-w-md">
          <Link to={`/posts/${p.id}/edit`} className="block truncate font-medium text-foreground hover:underline" title={p.title}>
            {p.title}
          </Link>
          <span className="block truncate text-xs text-muted">
            {p.categories.join(', ') || 'Sem categoria'}
            {p.cta_offer ? ` · oferta: ${p.cta_offer}` : ' · sem oferta (CTA)'}
          </span>
        </div>
      ),
    },
    {
      key: 'leads',
      header: 'Leads',
      className: 'text-right',
      render: (p) =>
        p.leads ? (
          <Link to={`/leads?post=${p.id}`} className="tabular-nums underline">
            {p.leads}
          </Link>
        ) : (
          <span className="text-subtle">0</span>
        ),
    },
    { key: 'qualified', header: 'Qualif.', className: 'text-right', render: (p) => <span className="tabular-nums">{p.qualified}</span> },
    { key: 'won', header: 'Vendas', className: 'text-right', render: (p) => <span className="font-medium tabular-nums">{p.won}</span> },
    { key: 'revenue', header: 'Receita', className: 'text-right', render: (p) => <span className="tabular-nums">{p.revenue ? formatCurrency(p.revenue) : '—'}</span> },
    ...(hasSearch
      ? ([
          {
            key: 'clicks',
            header: 'Cliques (Google)',
            className: 'text-right',
            render: (p) => (
              <span className="tabular-nums">
                {formatNumber(p.clicks, true)} <Trend current={p.clicks} previous={p.clicks_previous} />
              </span>
            ),
          },
          { key: 'position', header: 'Posição', className: 'text-right', render: (p) => <span className="tabular-nums">{p.position ?? '—'}</span> },
          { key: 'lead_rate', header: 'Conversão', className: 'text-right', render: (p) => <span className="tabular-nums">{formatPercent(p.lead_rate, 2)}</span> },
        ] satisfies Array<Column<ContentRow>>)
      : []),
  ]

  const groupRows = (groups: ContentGroup[]) =>
    groups.filter((g) => g.leads > 0).slice(0, 8).map((g) => ({
      key: g.name,
      label: g.name,
      value: g.leads,
      detail: `${g.posts} post(s) · ${g.won} venda(s) · ${formatCurrency(g.revenue)}`,
    }))

  return (
    <Page>
      <PageHeader
        title="Conteúdo que converte"
        description="Leads e vendas gerados por cada post do blog, cruzados com a busca orgânica do Google."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Relatórios' }, { label: 'Conteúdo' }]}
        actions={
          <div className="flex flex-wrap gap-2">
            <Select aria-label="Período" value={days} onChange={(e) => setDays(e.target.value)} options={PERIODS} className="w-44" />
            <Select aria-label="Ordenação" value={sort} onChange={(e) => setSort(e.target.value)} options={SORTS} className="w-48" />
          </div>
        }
      />

      <PageContent>
        {!hasSearch && !report.isPending && (
          <p className="rounded-lg bg-primary-soft px-4 py-3 text-sm text-foreground">
            Conecte o Search Console em <Link to="/settings/integracoes" className="font-medium underline">Integrações</Link> para ver cliques, posição e taxa de conversão de cada post.
          </p>
        )}

        <Card>
          <DataTable caption="Desempenho por post" columns={columns} rows={rows} rowKey={(p) => p.id} loading={report.isPending} emptyState={<p className="p-6 text-center text-sm text-muted">Nenhum post publicado.</p>} />
        </Card>

        <div className="grid gap-5 lg:grid-cols-2">
          <Card>
            <CardHeader title="Categorias que geram leads" description="Passe o mouse para ver vendas e receita." />
            <CardContent className="pt-0">
              {groupRows(report.data?.categories ?? []).length ? <BarList rows={groupRows(report.data!.categories)} /> : <p className="text-sm text-muted">Sem leads atribuídos a posts no período.</p>}
            </CardContent>
          </Card>
          <Card>
            <CardHeader title="Tags que geram leads" description="Bons temas para os próximos posts." />
            <CardContent className="pt-0">
              {groupRows(report.data?.tags ?? []).length ? <BarList rows={groupRows(report.data!.tags)} /> : <p className="text-sm text-muted">Sem leads atribuídos a posts no período.</p>}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader
            title={
              <span className="flex items-center gap-2">
                <RefreshCcw className="size-4 text-muted" aria-hidden="true" />
                Posts para atualizar
              </span>
            }
            description="Antigos, perdendo tráfego, perto da 1ª página ou com visitas sem leads."
          />
          {(stale.data ?? []).length === 0 ? (
            <CardContent className="pt-0 text-sm text-muted">{stale.isPending ? 'Carregando…' : 'Nada pendente. 👌'}</CardContent>
          ) : (
            <ul className="divide-y divide-surface-2">
              {(stale.data ?? []).map((post) => (
                <li key={post.id} className="flex flex-col gap-1.5 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <Link to={`/posts/${post.id}/edit`} className="block truncate text-sm font-medium text-foreground hover:underline">
                      {post.title}
                    </Link>
                    <span className="text-xs text-muted">Atualizado em {formatDate(post.updated_at)}</span>
                  </div>
                  <div className="flex flex-wrap gap-1.5">
                    {post.reasons.map((r) => (
                      <Badge key={r.type} variant={REASON_BADGE[r.type]}>
                        {r.label}
                      </Badge>
                    ))}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </PageContent>
    </Page>
  )
}
