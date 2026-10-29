import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Download, MessageSquareText, Pencil } from 'lucide-react'
import {
  Badge,
  Button,
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  Select,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime } from '@/shared/utils/format'
import { LeadEditModal } from '../components/LeadEditModal'
import { useExportGoogleAds, useLeadsQuery } from '../hooks/useLeads'
import { LEAD_STATUS_LABELS, LEAD_STATUS_OPTIONS, type Lead, type LeadStatus } from '../services/leads.service'

const STATUS_BADGE: Record<LeadStatus, 'neutral' | 'primary' | 'success' | 'warning' | 'danger'> = {
  new: 'primary',
  qualified: 'warning',
  won: 'success',
  lost: 'neutral',
  spam: 'danger',
}

export default function LeadsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [ref, setRef] = useState(searchParams.get('ref') ?? '')
  const debouncedRef = useDebounce(ref)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const { can } = usePermissions()
  const exportGoogleAds = useExportGoogleAds()
  const [editing, setEditing] = useState<Lead | null>(null)

  const query = useLeadsQuery({
    page,
    per_page: 15,
    ref: debouncedRef.trim() || undefined,
    status: status || undefined,
  })

  const updateParams = (next: Record<string, string>) =>
    setSearchParams(
      (params) => {
        for (const [key, value] of Object.entries(next)) {
          if (value) params.set(key, value)
          else params.delete(key)
        }
        return params
      },
      { replace: true },
    )

  const columns: Array<Column<Lead>> = [
    {
      key: 'ref',
      header: 'Código',
      render: (lead) => <span className="font-mono font-medium text-foreground">{lead.ref}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (lead) => <Badge variant={STATUS_BADGE[lead.status]}>{LEAD_STATUS_LABELS[lead.status]}</Badge>,
    },
    {
      key: 'origin',
      header: 'Origem',
      render: (lead) => (
        <div className="min-w-0">
          <span className="block text-foreground">
            {[lead.utm_source ?? 'google', lead.utm_medium ?? 'cpc'].join(' / ')}
          </span>
          <span className="block max-w-56 truncate text-xs text-muted" title={lead.landing_path ?? undefined}>
            {lead.landing_path ?? '—'}
          </span>
        </div>
      ),
    },
    {
      key: 'contacted_at',
      header: 'Contato em',
      render: (lead) => <span className="text-muted">{formatDateTime(lead.contacted_at)}</span>,
    },
    {
      key: 'notes',
      header: 'Observações',
      render: (lead) => (
        <span className="block max-w-64 truncate text-muted" title={lead.notes ?? undefined}>
          {lead.notes ?? '—'}
        </span>
      ),
    },
    ...(can(Permission.LEAD_UPDATE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-16 text-right',
            render: (lead: Lead) => (
              <Button variant="ghost" size="sm" onClick={() => setEditing(lead)} aria-label={`Qualificar lead ${lead.ref}`}>
                <Pencil className="size-4" />
              </Button>
            ),
          } satisfies Column<Lead>,
        ]
      : []),
  ]

  const filtered = Boolean(debouncedRef || status)

  return (
    <Page>
      <PageHeader
        title="Leads"
        description="Contatos de WhatsApp vindos de anúncios. Busque pelo código que aparece na mensagem do cliente."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Leads' }]}
        actions={
          <Can permission={Permission.LEAD_READ}>
            <Button variant="secondary" onClick={() => exportGoogleAds.mutate()} loading={exportGoogleAds.isPending}>
              <Download className="size-4" />
              Exportar para Google Ads
            </Button>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Código (ex.: NX-7K4P2Q)"
            value={ref}
            onChange={(e) => {
              setRef(e.target.value)
              updateParams({ ref: e.target.value, page: '' })
            }}
          />
          <Select
            options={[{ value: '', label: 'Todos' }, ...LEAD_STATUS_OPTIONS]}
            value={status}
            onChange={(e) => updateParams({ status: e.target.value, page: '' })}
            className="w-48"
          />
        </FilterBar>

        <DataTable
          caption="Leads"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(lead) => lead.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={MessageSquareText}
              title={filtered ? 'Nenhum resultado' : 'Nenhum lead ainda'}
              description={
                filtered
                  ? 'Confira o código digitado ou ajuste o filtro de status.'
                  : 'Os leads aparecem quando alguém vindo de um anúncio clica no WhatsApp do site.'
              }
            />
          }
        />
        {query.data && <Pagination meta={query.data.meta} onPageChange={(n) => updateParams({ page: String(n) })} />}
      </PageContent>

      {editing && <LeadEditModal key={editing.id} lead={editing} onClose={() => setEditing(null)} />}
    </Page>
  )
}
