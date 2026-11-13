import { useState, type DragEvent } from 'react'
import { Plus, Settings2 } from 'lucide-react'
import { Button, ButtonLink, Loading, Page, PageHeader, SearchInput } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { cn } from '@/shared/utils/cn'
import type { Lead } from '@/modules/leads/services/leads.service'
import { LeadCard } from '../components/LeadCard'
import { LeadDetailModal } from '../components/LeadDetailModal'
import { NewLeadModal } from '../components/NewLeadModal'
import { stageColor } from '../constants'
import { useBoardQuery, useMoveLead } from '../hooks/useCrm'

/** Funil de vendas em kanban: arraste o cartão para mudar a etapa. */
export default function PipelineBoardPage() {
  const [search, setSearch] = useState('')
  const debounced = useDebounce(search).trim()
  const board = useBoardQuery(debounced)
  const moveLead = useMoveLead(debounced)
  const { can } = usePermissions()
  const canMove = can(Permission.LEAD_UPDATE)
  const [dragging, setDragging] = useState<Lead | null>(null)
  const [overStage, setOverStage] = useState<string | null>(null)
  const [openLeadId, setOpenLeadId] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)

  const stages = board.data ?? []
  const openLead = stages.flatMap((stage) => stage.leads).find((lead) => lead.id === openLeadId)

  const onDragStart = (event: DragEvent, lead: Lead) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', lead.id)
    setDragging(lead)
  }

  const onDrop = (event: DragEvent, stageId: string) => {
    event.preventDefault()
    const leadId = event.dataTransfer.getData('text/plain') || dragging?.id
    setOverStage(null)
    setDragging(null)
    if (leadId && dragging?.stage?.id !== stageId) moveLead.mutate({ leadId, stageId })
  }

  return (
    <Page className="flex h-[calc(100dvh-4.5rem)] min-h-0 flex-col gap-4 pb-2">
      <PageHeader
        title="Funil"
        description="Arraste os cartões entre as etapas. Qualificado e Fechado entram na conversão offline do Google Ads."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Funil' }]}
        actions={
          <div className="flex flex-wrap gap-2">
            <Can permission={Permission.PIPELINE_MANAGE}>
              <ButtonLink to="/crm/etapas" variant="secondary">
                <Settings2 className="size-4" />
                Etapas
              </ButtonLink>
            </Can>
            <Can permission={Permission.LEAD_UPDATE}>
              <Button onClick={() => setCreating(true)}>
                <Plus className="size-4" />
                Novo lead
              </Button>
            </Can>
          </div>
        }
      />

      <div className="max-w-sm">
        <SearchInput placeholder="Buscar por nome, empresa, telefone ou código" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>

      {board.isPending ? (
        <Loading />
      ) : (
        <div className="-mx-6 min-h-0 flex-1 overflow-x-auto px-6 pb-2 lg:-mx-10 lg:px-10" role="list" aria-label="Etapas do funil">
          <div className="flex h-full min-h-[20rem] gap-4">
            {stages.map((stage) => {
              const color = stageColor(stage.color)

              return (
                <section
                  key={stage.id}
                  role="listitem"
                  aria-label={`${stage.name}: ${stage.total} lead(s)`}
                  onDragOver={(event) => {
                    if (!dragging || !canMove) return
                    event.preventDefault()
                    setOverStage(stage.id)
                  }}
                  onDragLeave={() => setOverStage((current) => (current === stage.id ? null : current))}
                  onDrop={(event) => onDrop(event, stage.id)}
                  className={cn(
                    'flex w-72 shrink-0 flex-col rounded-xl border-t-4 bg-surface-2/60 transition-colors',
                    color.border,
                    overStage === stage.id && 'bg-primary-soft ring-2 ring-primary/40',
                  )}
                >
                  <header className="flex items-center justify-between gap-2 px-3 pt-3 pb-2">
                    <h2 className="flex min-w-0 items-center gap-2 text-sm font-semibold text-foreground">
                      <span className="truncate">{stage.name}</span>
                    </h2>
                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', color.soft)}>{stage.total}</span>
                  </header>

                  <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2">
                    {stage.leads.map((lead) => (
                      <LeadCard
                        key={lead.id}
                        lead={lead}
                        draggable={canMove}
                        dragging={dragging?.id === lead.id}
                        onOpen={(item) => setOpenLeadId(item.id)}
                        onDragStart={onDragStart}
                        onDragEnd={() => {
                          setDragging(null)
                          setOverStage(null)
                        }}
                      />
                    ))}
                    {stage.leads.length === 0 && (
                      <p className="rounded-lg border border-dashed border-surface-3 px-3 py-6 text-center text-xs text-subtle">
                        {debounced ? 'Nenhum resultado' : 'Nenhum lead nesta etapa'}
                      </p>
                    )}
                    {stage.total > stage.leads.length && (
                      <p className="px-1 text-center text-xs text-muted">
                        Mostrando {stage.leads.length} de {stage.total}. Use a busca para encontrar os demais.
                      </p>
                    )}
                  </div>
                </section>
              )
            })}
          </div>
        </div>
      )}

      {openLead && (
        <LeadDetailModal key={openLead.id} lead={openLead} stages={stages} search={debounced} onClose={() => setOpenLeadId(null)} />
      )}
      {creating && <NewLeadModal stages={stages} onClose={() => setCreating(false)} />}
    </Page>
  )
}
