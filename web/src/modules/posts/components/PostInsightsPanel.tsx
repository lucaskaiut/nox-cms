import { Link } from 'react-router'
import { Check, Copy, Link2, Search, TrendingUp } from 'lucide-react'
import { Badge, Card, CardContent, CardHeader, Skeleton } from '@/shared/design-system'
import { toast } from '@/shared/stores/toast.store'
import { formatCurrency, formatNumber, formatRelative } from '@/shared/utils/format'
import { useLinkSuggestions, usePostPerformance } from '@/modules/reports/hooks/useReports'
import type { LinkSuggestion } from '@/modules/reports/services/reports.service'

function copyLink(s: LinkSuggestion) {
  const href = s.url ?? `/${s.slug}`
  void navigator.clipboard.writeText(`<a href="${href}">${s.title}</a>`)
  toast.success('Link copiado', 'Cole no editor (modo HTML) onde fizer sentido no texto.')
}

/** Ao lado do editor: o que este post gerou e sugestões de links internos. */
export function PostInsightsPanel({ postId }: { postId: string }) {
  const perf = usePostPerformance(postId)
  const links = useLinkSuggestions(postId)
  const p = perf.data

  return (
    <aside className="space-y-5 xl:sticky xl:top-20" aria-label="Desempenho e links internos">
      <Card>
        <CardHeader
          title={
            <span className="flex items-center gap-2">
              <TrendingUp className="size-4 text-muted" aria-hidden="true" />
              Resultado do post
            </span>
          }
        />
        <CardContent className="space-y-4 pt-0">
          {perf.isPending || !p ? (
            <Skeleton className="h-24" />
          ) : (
            <>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                {[
                  ['Leads', formatNumber(p.leads)],
                  ['Qualificados', formatNumber(p.qualified)],
                  ['Vendas', formatNumber(p.won)],
                  ['Receita', formatCurrency(p.revenue)],
                ].map(([label, value]) => (
                  <div key={label}>
                    <dt className="text-xs text-muted">{label}</dt>
                    <dd className="text-lg font-semibold text-foreground">{value}</dd>
                  </div>
                ))}
              </dl>

              {p.search ? (
                <div className="rounded-lg bg-surface-2/60 p-3 text-sm">
                  <p className="flex items-center gap-1.5 text-xs font-medium text-muted">
                    <Search className="size-3.5" aria-hidden="true" />
                    Google, últimos {p.search.days} dias
                  </p>
                  <p className="mt-1 text-foreground">
                    {formatNumber(p.search.clicks)} cliques · {formatNumber(p.search.impressions, true)} impressões · posição {p.search.position ?? '—'}
                  </p>
                  {p.search.clicks > 0 && (
                    <p className="mt-0.5 text-xs text-muted">
                      {((p.leads / p.search.clicks) * 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}% dos cliques viraram lead
                    </p>
                  )}
                </div>
              ) : (
                <p className="text-xs text-muted">Conecte o Search Console para ver cliques e posição deste post.</p>
              )}

              {p.offers.length > 0 && (
                <div>
                  <p className="text-xs font-medium text-muted">Ofertas que converteram</p>
                  <ul className="mt-1 space-y-0.5 text-sm">
                    {p.offers.map((o) => (
                      <li key={o.offer} className="flex justify-between gap-2">
                        <span className="truncate text-foreground">{o.offer}</span>
                        <span className="text-muted tabular-nums">{o.leads}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {p.recent_leads.length > 0 && (
                <div>
                  <p className="text-xs font-medium text-muted">Últimos leads</p>
                  <ul className="mt-1 space-y-1 text-sm">
                    {p.recent_leads.map((l) => (
                      <li key={l.id} className="flex items-center justify-between gap-2">
                        <Link to={`/crm?lead=${l.id}`} className="truncate text-foreground underline">
                          {l.name || l.ref}
                        </Link>
                        <span className="shrink-0 text-xs text-muted">
                          {l.status_label} · {formatRelative(l.created_at)}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader
          title={
            <span className="flex items-center gap-2">
              <Link2 className="size-4 text-muted" aria-hidden="true" />
              Links internos
            </span>
          }
          description="Ligue posts do mesmo tema. Isso ajuda no Google e leva o leitor aos posts que mais convertem."
        />
        <CardContent className="space-y-4 pt-0">
          {links.isPending ? (
            <Skeleton className="h-24" />
          ) : (links.data?.link_to.length ?? 0) === 0 ? (
            <p className="text-sm text-muted">Nenhum post relacionado encontrado. Use tags e categorias em comum.</p>
          ) : (
            <>
              <div>
                <p className="text-xs font-medium text-muted">Este post pode linkar para</p>
                <ul className="mt-1.5 space-y-2">
                  {links.data!.link_to.map((s) => (
                    <li key={s.id} className="flex items-start gap-2 text-sm">
                      <div className="min-w-0 flex-1">
                        <Link to={`/posts/${s.id}/edit`} className="line-clamp-2 text-foreground hover:underline">
                          {s.title}
                        </Link>
                        <span className="text-xs text-muted">
                          {s.shared_terms.length > 0 && `${s.shared_terms.join(', ')}`}
                          {s.leads > 0 && ` · ${s.leads} lead(s)`}
                        </span>
                      </div>
                      {s.already_linked ? (
                        <Badge variant="success">
                          <Check className="size-3" aria-hidden="true" />
                          Já linkado
                        </Badge>
                      ) : (
                        <button
                          type="button"
                          onClick={() => copyLink(s)}
                          className="flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs text-primary hover:bg-primary-soft"
                          aria-label={`Copiar link para ${s.title}`}
                        >
                          <Copy className="size-3.5" />
                          Copiar
                        </button>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
              {(links.data?.link_from.length ?? 0) > 0 && (
                <div>
                  <p className="text-xs font-medium text-muted">Posts que ainda não apontam para este</p>
                  <ul className="mt-1.5 space-y-1 text-sm">
                    {links.data!.link_from.map((s) => (
                      <li key={s.id}>
                        <Link to={`/posts/${s.id}/edit`} className="line-clamp-1 text-foreground underline">
                          {s.title}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </aside>
  )
}
