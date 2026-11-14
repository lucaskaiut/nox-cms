import { useState } from 'react'
import { Link } from 'react-router'
import { AlarmClock, Hourglass, Megaphone } from 'lucide-react'
import { Card, CardContent, CardHeader, DataTable, Select, Skeleton, type Column } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatCurrency, formatNumber, formatPercent } from '@/shared/utils/format'
import { stageColor } from '@/modules/crm/constants'
import { useStaleLeadsQuery, useTasksQuery } from '@/modules/crm/hooks/useCrm'
import { useFunnelReport } from '../hooks/useReports'
import type { FunnelReport } from '../services/reports.service'
import { BarList, DailyColumns, Delta, StatTile } from './Charts'
import { PERIODS, periodParams } from '../period'

type SourceRow = FunnelReport['by_source'][number]
type StageRow = FunnelReport['stages'][number]

function pct(part: number, whole: number) {
  return whole > 0 ? formatPercent((part / whole) * 100, 0) : '—'
}

/** Visão comercial: volume, conversão, receita, origem e gargalos do funil. */
export function FunnelDashboard() {
  const [days, setDays] = useState('30')
  const params = periodParams(days)
  const report = useFunnelReport(params)
  const { can } = usePermissions()
  const tasks = useTasksQuery({ scope: 'mine', status: 'open' }, can(Permission.LEAD_READ))
  const stale = useStaleLeadsQuery()
  const data = report.data
  const loading = report.isPending
  const t = data?.totals
  const p = data?.previous
  const overdue = (tasks.data ?? []).filter((task) => task.overdue).length

  const sourceColumns: Array<Column<SourceRow>> = [
    { key: 'label', header: 'Origem', render: (r) => <span className="font-medium text-foreground">{r.label}</span> },
    { key: 'leads', header: 'Leads', className: 'text-right', render: (r) => <span className="tabular-nums">{r.leads}</span> },
    { key: 'qualified', header: 'Qualificados', className: 'text-right', render: (r) => <span className="tabular-nums">{r.qualified} <span className="text-xs text-muted">({pct(r.qualified, r.leads)})</span></span> },
    { key: 'won', header: 'Vendas', className: 'text-right', render: (r) => <span className="tabular-nums">{r.won}</span> },
    { key: 'revenue', header: 'Receita', className: 'text-right', render: (r) => <span className="tabular-nums">{formatCurrency(r.revenue)}</span> },
  ]

  const stageColumns: Array<Column<StageRow>> = [
    {
      key: 'name',
      header: 'Etapa',
      render: (r) => (
        <span className="flex items-center gap-2 font-medium text-foreground">
          <span className={`size-2.5 rounded-full ${stageColor(r.color).dot}`} aria-hidden="true" />
          {r.name}
        </span>
      ),
    },
    { key: 'count', header: 'Leads agora', className: 'text-right', render: (r) => <span className="tabular-nums">{r.count}</span> },
    { key: 'value', header: 'Valor', className: 'text-right', render: (r) => <span className="tabular-nums">{r.value ? formatCurrency(r.value) : '—'}</span> },
    {
      key: 'avg',
      header: 'Tempo médio na etapa',
      className: 'text-right',
      render: (r) =>
        r.avg_days === null ? (
          <span className="text-subtle">—</span>
        ) : (
          <span className="tabular-nums" title={`${r.samples} passagem(ns) nos últimos 6 meses`}>
            {r.avg_days.toLocaleString('pt-BR')} dia(s)
          </span>
        ),
    },
  ]

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-foreground">Comercial</h2>
        <Select aria-label="Período" value={days} onChange={(e) => setDays(e.target.value)} options={PERIODS} className="w-48" />
      </div>

      {(overdue > 0 || (stale.data?.length ?? 0) > 0) && (
        <div className="flex flex-wrap gap-3">
          {overdue > 0 && (
            <Link to="/tarefas" className="flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger hover:underline">
              <AlarmClock className="size-4" aria-hidden="true" />
              {overdue} tarefa(s) atrasada(s)
            </Link>
          )}
          {(stale.data?.length ?? 0) > 0 && (
            <Link to="/tarefas" className="flex items-center gap-2 rounded-lg bg-warning-soft px-3 py-2 text-sm text-warning hover:underline">
              <Hourglass className="size-4" aria-hidden="true" />
              {stale.data!.length} lead(s) parado(s)
            </Link>
          )}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatTile label="Leads" loading={loading} value={formatNumber(t?.leads ?? 0)} delta={<Delta current={t?.leads ?? null} previous={p?.leads ?? null} />} />
        <StatTile
          label="Qualificados"
          loading={loading}
          value={formatNumber(t?.qualified ?? 0)}
          delta={<Delta current={t?.qualified ?? null} previous={p?.qualified ?? null} />}
        />
        <StatTile
          label="Vendas"
          loading={loading}
          value={formatNumber(t?.won ?? 0)}
          delta={<Delta current={t?.won ?? null} previous={p?.won ?? null} />}
          hint={t?.win_rate !== null && t?.win_rate !== undefined ? `${formatPercent(t.win_rate, 0)} dos negócios fechados` : undefined}
        />
        <StatTile
          label="Receita"
          loading={loading}
          value={formatCurrency(t?.revenue ?? 0)}
          delta={<Delta current={t?.revenue ?? null} previous={p?.revenue ?? null} />}
          hint={t?.avg_ticket ? `ticket médio ${formatCurrency(t.avg_ticket)}` : undefined}
        />
      </div>

      <div className="grid gap-5 xl:grid-cols-[1fr_20rem]">
        <Card>
          <CardHeader title="Leads por dia" description={data ? `${data.period.days} dias · passe o mouse para ver o dia` : undefined} />
          <CardContent className="pt-0">
            {loading || !data ? (
              <Skeleton className="h-48" />
            ) : (
              <DailyColumns
                label="Leads por dia"
                data={data.series.map((d) => ({ date: d.date, value: d.leads }))}
                valueLabel={(v) => `${v} lead(s)`}
                extra={(i) => (data.series[i].won ? `${data.series[i].won} venda(s)` : null)}
              />
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader title="Conversão dos leads do período" description="Dos leads que chegaram no período, quantos avançaram." />
          <CardContent className="space-y-4 pt-0">
            {loading || !data ? (
              <Skeleton className="h-40" />
            ) : (
              <>
                {[
                  { label: 'Chegaram', value: data.cohort.created, of: null },
                  { label: 'Qualificaram', value: data.cohort.qualified, of: data.cohort.created },
                  { label: 'Fecharam', value: data.cohort.won, of: data.cohort.created },
                ].map((step) => (
                  <div key={step.label}>
                    <div className="flex items-baseline justify-between text-sm">
                      <span className="text-muted">{step.label}</span>
                      <span className="font-semibold text-foreground tabular-nums">
                        {step.value}
                        {step.of !== null && <span className="ml-1 text-xs font-normal text-muted">({pct(step.value, step.of)})</span>}
                      </span>
                    </div>
                    <div className="mt-1 h-2 rounded-full bg-primary-soft">
                      <div className="h-2 rounded-full bg-primary" style={{ width: `${data.cohort.created ? (step.value / data.cohort.created) * 100 : 0}%` }} />
                    </div>
                  </div>
                ))}
                <dl className="grid grid-cols-2 gap-3 border-t border-surface-2 pt-3 text-sm">
                  <div>
                    <dt className="text-xs text-muted">Dias até fechar</dt>
                    <dd className="font-semibold text-foreground">{t?.avg_days_to_win ?? '—'}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted">Em negociação</dt>
                    <dd className="font-semibold text-foreground">{formatCurrency(t?.open_pipeline_value ?? 0)}</dd>
                  </div>
                </dl>
              </>
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader title="De onde vêm os clientes" description="Leads chegados no período e vendas fechadas no período, por origem." />
        <DataTable caption="Leads por origem" columns={sourceColumns} rows={data?.by_source ?? []} rowKey={(r) => r.source} loading={loading} skeletonRows={3} emptyState={<p className="p-6 text-center text-sm text-muted">Nenhum lead no período.</p>} />
      </Card>

      <div className="grid gap-5 lg:grid-cols-2">
        <Card>
          <CardHeader title="Etapas do funil" description="Onde os leads estão hoje e quanto tempo costumam ficar em cada etapa." />
          <DataTable caption="Etapas do funil" columns={stageColumns} rows={data?.stages ?? []} rowKey={(r) => r.id} loading={loading} skeletonRows={4} />
        </Card>

        <Card>
          <CardHeader title="Por que perdemos" description="Motivos informados ao marcar o lead como perdido." />
          <CardContent className="pt-0">
            {loading ? (
              <Skeleton className="h-32" />
            ) : (data?.lost_reasons.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">Nenhuma perda no período.</p>
            ) : (
              <BarList
                rows={data!.lost_reasons.map((r) => ({ key: r.reason, label: r.reason, value: r.count, detail: r.value ? `${formatCurrency(r.value)} em negociação perdida` : undefined }))}
                format={(v) => `${v}`}
              />
            )}
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader
          title={
            <span className="flex items-center gap-2">
              <Megaphone className="size-4 text-muted" aria-hidden="true" />
              Anúncios
            </span>
          }
          description="Custo calculado com o investimento lançado para o período."
          actions={
            can(Permission.INTEGRATION_MANAGE) ? (
              <Link to="/settings/investimento" className="text-sm text-primary hover:underline">
                Lançar investimento
              </Link>
            ) : undefined
          }
        />
        <CardContent className="grid gap-4 pt-0 sm:grid-cols-2 lg:grid-cols-5">
          {[
            { label: 'Investido', value: formatCurrency(data?.spend.total ?? 0) },
            { label: 'Leads de anúncio', value: formatNumber(data?.spend.paid_leads ?? 0) },
            { label: 'Custo por lead', value: formatCurrency(data?.spend.cost_per_lead, true) },
            { label: 'Custo por venda', value: formatCurrency(data?.spend.cost_per_won) },
            { label: 'Retorno (ROAS)', value: data?.spend.roas !== null && data?.spend.roas !== undefined ? `${data.spend.roas.toLocaleString('pt-BR')}×` : '—' },
          ].map((item) => (
            <div key={item.label}>
              <p className="text-xs text-muted">{item.label}</p>
              {loading ? <Skeleton className="mt-1 h-6 w-20" /> : <p className="text-lg font-semibold text-foreground">{item.value}</p>}
            </div>
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
