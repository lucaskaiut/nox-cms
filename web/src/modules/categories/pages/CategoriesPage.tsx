import { useState } from 'react'
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react'
import { Button, ButtonLink, ConfirmDialog, DataTable, EmptyState, Page, PageContent, PageHeader, type Column, Modal, Form, TextField, TextareaField } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/shared/stores/toast.store'
import { categoriesService, type Category } from '../../posts/services/posts.service'

const catSchema = z.object({ name: z.string().min(1, 'Informe o nome'), description: z.string(), parent_id: z.number().nullable() })
type CatForm = z.infer<typeof catSchema>

const catQueryKeys = { all: ['categories'] as const, list: () => ['categories', 'list'] as const }

export default function CategoriesPage() {
  const { can } = usePermissions()
  const qc = useQueryClient()
  const query = useQuery({ queryKey: catQueryKeys.list(), queryFn: categoriesService.list })
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [toDelete, setToDelete] = useState<Category | null>(null)

  const saveMutation = useMutation({
    mutationFn: (data: CatForm) => editing ? categoriesService.update(editing.id, { ...data, parent_id: data.parent_id }) : categoriesService.create({ ...data, parent_id: data.parent_id ?? undefined }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: catQueryKeys.all }); setModalOpen(false); setEditing(null); toast.success(editing ? 'Categoria atualizada' : 'Categoria criada') },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => categoriesService.remove(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: catQueryKeys.all }); setToDelete(null); toast.success('Categoria removida') },
  })

  const form = useForm<CatForm>({ resolver: zodResolver(catSchema), defaultValues: { name: '', description: '', parent_id: null } })

  const openEdit = (cat: Category) => {
    setEditing(cat); form.reset({ name: cat.name, description: cat.description ?? '', parent_id: cat.parent_id }); setModalOpen(true)
  }

  const openCreate = () => {
    setEditing(null); form.reset({ name: '', description: '', parent_id: null }); setModalOpen(true)
  }

  const handleSubmit = async (values: CatForm) => {
    try { await saveMutation.mutateAsync(values) } catch (error) { if (isApiError(error) && error.status === 422) applyApiErrorsToForm(form, error) }
  }

  const categories = query.data ?? []

  const columns: Array<Column<Category>> = [
    { key: 'name', header: 'Nome', render: (cat) => <span className="font-medium text-foreground">{cat.name}</span> },
    { key: 'description', header: 'Descrição', render: (cat) => <span className="text-muted">{cat.description || '—'}</span> },
    { key: 'children', header: 'Subcategorias', render: (cat) => <span className="text-muted">{cat.children?.length || 0}</span> },
    { key: 'actions', header: <span className="sr-only">Ações</span>, className: 'w-24 text-right', render: (cat) => (
      <div className="flex items-center justify-end gap-1">
        {can(Permission.POST_UPDATE) && <Button variant="ghost" size="sm" onClick={() => openEdit(cat)} aria-label={`Editar ${cat.name}`}><Pencil className="size-4" /></Button>}
        {can(Permission.POST_DELETE) && <Button variant="ghost" size="sm" onClick={() => setToDelete(cat)} aria-label={`Excluir ${cat.name}`} className="text-danger hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></Button>}
      </div>
    )},
  ]

  return (
    <Page>
      <PageHeader title="Categorias" description="Organize o conteúdo em categorias e subcategorias." breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Categorias' }]} actions={<Can permission={Permission.POST_CREATE}><ButtonLink to="#" onClick={(e) => { e.preventDefault(); openCreate() }}><Plus className="size-4" />Nova categoria</ButtonLink></Can>} />
      <PageContent>
        <DataTable caption="Categorias" columns={columns} rows={categories} rowKey={(cat) => cat.id} loading={query.isPending}
          emptyState={<EmptyState icon={FolderTree} title="Nenhuma categoria" description="Crie categorias para organizar os posts." action={<Can permission={Permission.POST_CREATE}><Button variant="primary" size="sm" onClick={openCreate}><Plus className="size-4" />Nova categoria</Button></Can>} />} />
      </PageContent>

      <Modal open={modalOpen} onClose={() => { setModalOpen(false); setEditing(null) }} title={editing ? 'Editar categoria' : 'Nova categoria'} size="sm"
        footer={<><Button variant="secondary" onClick={() => { setModalOpen(false); setEditing(null) }}>Cancelar</Button><Button variant="primary" onClick={form.handleSubmit(handleSubmit)} loading={saveMutation.isPending}>Salvar</Button></>}>
        <Form form={form} onSubmit={handleSubmit} className="space-y-4">
          <TextField name="name" label="Nome" required />
          <TextareaField name="description" label="Descrição" rows={3} />
        </Form>
      </Modal>

      <ConfirmDialog open={toDelete !== null} onClose={() => setToDelete(null)} onConfirm={() => { if (toDelete) deleteMutation.mutate(toDelete.id) }} loading={deleteMutation.isPending} title="Excluir categoria" description={<>Tem certeza que deseja excluir <strong>{toDelete?.name}</strong>?</>} confirmLabel="Excluir" />
    </Page>
  )
}
