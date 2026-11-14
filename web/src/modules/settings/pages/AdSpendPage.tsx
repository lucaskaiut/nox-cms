import { useState, type FormEvent } from 'react'
import { Pencil, Trash2 } from 'lucide-react'
import { Button, Card, CardContent, CardHeader, DataTable, Field, Input, Page, PageContent, PageHeader, Select, type Column } from '@/shared/design-system'
import { formatCurrency, formatDate, toDateInput } from '@/shared/utils/format'
import { useAdSpends, useDeleteAdSpend, useSaveAdSpend } from '@/modules/reports/hooks/useReports'
import { AD_CHANNELS, type AdSpend } from '@/modules/reports/services/reports.service'

function monthRange(offset = 0) {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() + offset, 1)
  const end = new Date(now.getFullYear(), now.getMonth() + offset + 1, 0)
  return { period_start: toDateInput(start), period_end: toDateInput(end) }
}

const EMPTY = { id: undefined as string | undefined, channel: 'google_ads', amount: '', notes: '', ...monthRange() }

/** Lançamentos de investimento em mídia, base do custo por lead e do ROAS. */
export default function AdSpendPage() {
  const spends = useAdSpends()
  const save = useSaveAdSpend()
  const remove = useDeleteAdSpend()
  const [form, setForm] = useState(EMPTY)
  const errors = save.error?.fieldErrors ?? {}

  const submit = (event: FormEvent) => {
    event.preventDefault()
    save.mutate(
      { id: form.id, channel: form.channel, period_start: form.period_start, period_end: form.period_end, amount: Number(form.amount), notes: form.notes.trim() || null },
      { onSuccess: () => setForm(EMPTY) },
    )
  }

  const columns: Array<Column<AdSpend>> = [
    { key: 'channel', header: 'Canal', render: (s) => <span className="font-medium text-foreground">{s.channel_label}</span> },
    { key: 'period', header: 'Período', render: (s) => `${formatDate(s.period_start)} a ${formatDate(s.period_end)}` },
    { key: 'amount', header: 'Valor', className: 'text-right', render: (s) => <span className="tabular-nums">{formatCurrency(s.amount, true)}</span> },
    { key: 'notes', header: 'Observação', render: (s) => <span className="text-muted">{s.notes ?? '—'}</span> },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (s) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="sm" aria-label="Editar" onClick={() => setForm({ id: s.id, channel: s.channel, amount: String(s.amount), notes: s.notes ?? '', period_start: s.period_start, period_end: s.period_end })}>
            <Pencil className="size-4" />
          </Button>
          <Button variant="ghost" size="sm" aria-label="Excluir" className="text-danger hover:bg-danger-soft hover:text-danger" onClick={() => remove.mutate(s.id)}>
            <Trash2 className="size-4" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Investimento em anúncios"
        description="Lance quanto foi gasto por período (ex.: fatura mensal do Google Ads). O dashboard calcula custo por lead, custo por venda e ROAS."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Investimento' }]}
      />
      <PageContent>
        <Card>
          <CardHeader title={form.id ? 'Editar lançamento' : 'Novo lançamento'} />
          <CardContent className="pt-0">
            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
              <Field label="Canal" htmlFor="sp-channel">
                <Select id="sp-channel" value={form.channel} onChange={(e) => setForm((f) => ({ ...f, channel: e.target.value }))} options={AD_CHANNELS} />
              </Field>
              <Field label="De" htmlFor="sp-start" error={errors.period_start?.[0]}>
                <Input id="sp-start" type="date" value={form.period_start} onChange={(e) => setForm((f) => ({ ...f, period_start: e.target.value }))} required />
              </Field>
              <Field label="Até" htmlFor="sp-end" error={errors.period_end?.[0]}>
                <Input id="sp-end" type="date" value={form.period_end} onChange={(e) => setForm((f) => ({ ...f, period_end: e.target.value }))} required />
              </Field>
              <Field label="Valor (R$)" htmlFor="sp-amount" error={errors.amount?.[0]}>
                <Input id="sp-amount" type="number" min={0} step="0.01" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} required />
              </Field>
              <Field label="Observação" htmlFor="sp-notes">
                <Input id="sp-notes" value={form.notes} maxLength={255} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} />
              </Field>
              <div className="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
                <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, ...monthRange(-1) }))}>
                  Mês passado
                </Button>
                <Button variant="ghost" size="sm" onClick={() => setForm((f) => ({ ...f, ...monthRange() }))}>
                  Este mês
                </Button>
                {form.id && (
                  <Button variant="secondary" onClick={() => setForm(EMPTY)}>
                    Cancelar
                  </Button>
                )}
                <Button type="submit" loading={save.isPending}>
                  {form.id ? 'Salvar' : 'Lançar'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <DataTable caption="Lançamentos" columns={columns} rows={spends.data ?? []} rowKey={(s) => s.id} loading={spends.isPending} emptyState={<p className="p-6 text-center text-sm text-muted">Nenhum investimento lançado.</p>} />
        </Card>
      </PageContent>
    </Page>
  )
}
