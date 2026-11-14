import { useState, type ReactNode } from 'react'
import { ArrowDownRight, ArrowUpRight } from 'lucide-react'
import { Card, CardContent, Skeleton } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'

/** Variação percentual vs. período anterior; `upIsGood` define a cor. */
export function Delta({ current, previous, upIsGood = true }: { current: number | null; previous: number | null; upIsGood?: boolean }) {
  if (current === null || previous === null || previous === 0) return null
  const pct = ((current - previous) / previous) * 100
  if (!Number.isFinite(pct) || Math.abs(pct) < 0.5) return <span className="text-xs text-muted">= período anterior</span>
  const up = pct > 0
  const good = up === upIsGood
  const Icon = up ? ArrowUpRight : ArrowDownRight

  return (
    <span className={cn('inline-flex items-center gap-0.5 text-xs font-medium', good ? 'text-success' : 'text-danger')}>
      <Icon className="size-3.5" aria-hidden="true" />
      {up ? '+' : ''}
      {pct.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}%
      <span className="sr-only"> em relação ao período anterior</span>
    </span>
  )
}

export function StatTile({ label, value, hint, delta, loading }: { label: string; value: ReactNode; hint?: ReactNode; delta?: ReactNode; loading?: boolean }) {
  return (
    <Card>
      <CardContent className="space-y-1">
        <p className="text-[13px] text-muted">{label}</p>
        {loading ? <Skeleton className="h-8 w-24" /> : <p className="text-2xl font-semibold tracking-tight text-foreground">{value}</p>}
        <div className="flex min-h-4 flex-wrap items-center gap-2 text-xs text-muted">
          {delta}
          {hint}
        </div>
      </CardContent>
    </Card>
  )
}

/**
 * Colunas diárias (série única, sem legenda: o título nomeia). Barras finas,
 * ponta arredondada, tooltip por coluna com alvo de hover maior que a barra.
 */
export function DailyColumns({
  data,
  label,
  valueLabel,
  extra,
}: {
  data: Array<{ date: string; value: number }>
  label: string
  valueLabel: (v: number) => string
  extra?: (index: number) => string | null
}) {
  const [hover, setHover] = useState<number | null>(null)
  const max = Math.max(1, ...data.map((d) => d.value))
  const ticks = niceTicks(max)
  const top = ticks[ticks.length - 1]
  const fmt = (date: string) => new Date(`${date}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
  const labelEvery = Math.ceil(data.length / 8)

  return (
    <figure className="relative" aria-label={label}>
      <div className="flex h-48 gap-2">
        <div className="flex flex-col-reverse justify-between pb-5 text-right text-[11px] text-subtle tabular-nums" aria-hidden="true">
          {ticks.map((t) => (
            <span key={t}>{t}</span>
          ))}
        </div>
        <div className="relative flex-1">
          <div className="absolute inset-x-0 top-0 bottom-5 flex flex-col-reverse justify-between" aria-hidden="true">
            {ticks.map((t) => (
              <span key={t} className="h-px w-full bg-surface-2" />
            ))}
          </div>
          <div className="absolute inset-x-0 top-0 bottom-5 flex items-end">
            {data.map((d, i) => (
              <div
                key={d.date}
                className="flex h-full flex-1 cursor-default items-end justify-center"
                onMouseEnter={() => setHover(i)}
                onMouseLeave={() => setHover(null)}
                onFocus={() => setHover(i)}
                onBlur={() => setHover(null)}
                tabIndex={0}
                aria-label={`${fmt(d.date)}: ${valueLabel(d.value)}`}
              >
                <span
                  className={cn('w-full max-w-6 rounded-t-[4px] bg-primary transition-opacity', hover !== null && hover !== i && 'opacity-50')}
                  style={{ height: d.value ? `${(d.value / top) * 100}%` : 0, marginInline: 1 }}
                />
              </div>
            ))}
          </div>
          <div className="absolute inset-x-0 bottom-0 flex h-4 text-[11px] text-subtle" aria-hidden="true">
            {data.map((d, i) => (
              <span key={d.date} className="flex-1 text-center">
                {i % labelEvery === 0 ? fmt(d.date) : ''}
              </span>
            ))}
          </div>
          {hover !== null && data[hover] && (
            <div
              role="tooltip"
              className="pointer-events-none absolute -top-2 z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-foreground px-2.5 py-1.5 text-xs whitespace-nowrap text-background shadow-pop"
              style={{ left: `${((hover + 0.5) / data.length) * 100}%` }}
            >
              <span className="block font-medium">{fmt(data[hover].date)}</span>
              {valueLabel(data[hover].value)}
              {extra?.(hover) && <span className="block opacity-80">{extra(hover)}</span>}
            </div>
          )}
        </div>
      </div>
    </figure>
  )
}

/** Barras horizontais (magnitude, uma cor) com valor na ponta e tooltip nativo. */
export function BarList({
  rows,
  format = (v) => String(v),
}: {
  rows: Array<{ key: string; label: ReactNode; value: number; detail?: string }>
  format?: (v: number) => string
}) {
  const max = Math.max(1, ...rows.map((r) => r.value))

  return (
    <ul className="space-y-2.5">
      {rows.map((row) => (
        <li key={row.key} className="grid grid-cols-[minmax(0,9rem)_1fr] items-center gap-3 text-sm sm:grid-cols-[minmax(0,12rem)_1fr]" title={row.detail}>
          <span className="truncate text-foreground">{row.label}</span>
          <span className="flex items-center gap-2">
            <span className="h-3 rounded-r-[4px] bg-primary" style={{ width: `${Math.max((row.value / max) * 100, row.value ? 2 : 0)}%` }} />
            <span className="shrink-0 text-xs text-muted tabular-nums">{format(row.value)}</span>
          </span>
        </li>
      ))}
    </ul>
  )
}

function niceTicks(max: number): number[] {
  const step = max <= 4 ? 1 : max <= 10 ? 2 : Math.pow(10, Math.floor(Math.log10(max))) * (max / Math.pow(10, Math.floor(Math.log10(max))) > 5 ? 2 : 1)
  const top = Math.ceil(max / step) * step
  const out: number[] = []
  for (let v = 0; v <= top; v += step) out.push(v)
  return out
}
