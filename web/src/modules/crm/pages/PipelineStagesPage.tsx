import { useState } from 'react'
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge, Button, Card, Field, Loading, Modal, Page, PageContent, PageHeader, Select } from '@/shared/design-system'
import { STAGE_COLORS, stageColor } from '../constants'
import { StageFormModal } from '../components/StageFormModal'
import { useDeleteStage, useReorderStages, useStagesQuery } from '../hooks/useCrm'
import type { PipelineStage } from '../services/crm.service'

const OUTCOME_BADGE = { open: 'neutral', qualified: 'warning', won: 'success', lost: 'danger' } as const

/** Cadastro das etapas do funil: nome, cor, significado e ordem das colunas. */
export default function PipelineStagesPage() {
  const stagesQuery = useStagesQuery()
  const reorder = useReorderStages()
  const deleteStage = useDeleteStage()
  const [editing, setEditing] = useState<PipelineStage | 'new' | null>(null)
  const [removing, setRemoving] = useState<PipelineStage | null>(null)
  const [moveTo, setMoveTo] = useState('')
  const stages = stagesQuery.data ?? []

  const move = (index: number, direction: -1 | 1) => {
    const ids = stages.map((stage) => stage.id)
    const target = index + direction
    ;[ids[index], ids[target]] = [ids[target], ids[index]]
    reorder.mutate(ids)
  }

  const confirmRemove = () => {
    if (!removing) return
    deleteStage.mutate({ id: removing.id, moveTo: moveTo || null }, { onSuccess: () => setRemoving(null) })
  }

  return (
    <Page>
      <PageHeader
        title="Etapas do funil"
        description="As colunas do kanban. O significado define o status do lead e o que vai para o Google Ads como conversão offline."
        breadcrumb={[{ label: 'Funil', to: '/crm' }, { label: 'Etapas' }]}
        actions={
          <Button onClick={() => setEditing('new')}>
            <Plus className="size-4" />
            Nova etapa
          </Button>
        }
      />

      <PageContent>
        {stagesQuery.isPending ? (
          <Loading />
        ) : (
          <Card>
            <ol className="divide-y divide-surface-3">
              {stages.map((stage, index) => (
                <li key={stage.id} className="flex flex-wrap items-center gap-3 px-4 py-3 sm:flex-nowrap">
                  <div className="flex flex-col">
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 px-1.5"
                      disabled={index === 0 || reorder.isPending}
                      onClick={() => move(index, -1)}
                      aria-label={`Subir ${stage.name}`}
                    >
                      <ArrowUp className="size-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 px-1.5"
                      disabled={index === stages.length - 1 || reorder.isPending}
                      onClick={() => move(index, 1)}
                      aria-label={`Descer ${stage.name}`}
                    >
                      <ArrowDown className="size-3.5" />
                    </Button>
                  </div>
                  <span className={`size-3 shrink-0 rounded-full ${stageColor(stage.color).dot}`} title={STAGE_COLORS[stage.color]?.label} />
                  <span className="min-w-0 flex-1 truncate font-medium text-foreground">{stage.name}</span>
                  <Badge variant={OUTCOME_BADGE[stage.outcome]}>{stage.outcome_label}</Badge>
                  <span className="w-20 text-right text-sm text-muted">{stage.leads_count ?? 0} lead(s)</span>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="sm" onClick={() => setEditing(stage)} aria-label={`Editar ${stage.name}`}>
                      <Pencil className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      disabled={stages.length <= 1}
                      onClick={() => {
                        setMoveTo('')
                        setRemoving(stage)
                      }}
                      aria-label={`Remover ${stage.name}`}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                </li>
              ))}
            </ol>
          </Card>
        )}
      </PageContent>

      {editing && <StageFormModal stage={editing === 'new' ? undefined : editing} onClose={() => setEditing(null)} />}

      {removing && (
        <Modal
          open
          size="sm"
          onClose={() => setRemoving(null)}
          title={`Remover "${removing.name}"?`}
          description={
            (removing.leads_count ?? 0) > 0
              ? `Esta etapa tem ${removing.leads_count} lead(s). Escolha para onde movê-los.`
              : 'A etapa está vazia e será removida.'
          }
          footer={
            <>
              <Button variant="secondary" onClick={() => setRemoving(null)}>
                Cancelar
              </Button>
              <Button
                variant="danger"
                onClick={confirmRemove}
                loading={deleteStage.isPending}
                disabled={(removing.leads_count ?? 0) > 0 && !moveTo}
              >
                Remover
              </Button>
            </>
          }
        >
          {(removing.leads_count ?? 0) > 0 && (
            <Field label="Mover leads para" htmlFor="move-to">
              <Select
                id="move-to"
                value={moveTo}
                onChange={(e) => setMoveTo(e.target.value)}
                placeholder="Escolha uma etapa"
                options={stages.filter((s) => s.id !== removing.id).map((s) => ({ value: s.id, label: s.name }))}
              />
            </Field>
          )}
        </Modal>
      )}
    </Page>
  )
}
