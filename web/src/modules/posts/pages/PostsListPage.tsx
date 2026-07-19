import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { FileText, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge, Button, ButtonLink, ConfirmDialog, DataTable, EmptyState, FilterBar, Page, PageContent, PageHeader, Pagination, SearchInput, Select, type Column } from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatDate } from '@/shared/utils/format'
import { useDeletePost, usePostsQuery } from '../hooks/usePosts'
import { POST_STATUS_LABELS, POST_STATUS_OPTIONS, type Post, type PostStatus } from '../services/posts.service'

const STATUS_BADGE: Record<PostStatus, 'neutral' | 'primary' | 'warning' | 'success'> = { draft: 'neutral', review: 'warning', scheduled: 'primary', published: 'success', archived: 'neutral' }

export default function PostsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const statusFilter = searchParams.get('status') ?? ''
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [postToDelete, setPostToDelete] = useState<Post | null>(null)
  const deletePost = useDeletePost()

  const query = usePostsQuery({ page, per_page: 10, search: debouncedSearch || undefined, status: statusFilter || undefined })

  const updateParams = (next: Record<string, string>) => setSearchParams((params) => { for (const [k, v] of Object.entries(next)) { v ? params.set(k, v) : params.delete(k) }; if (next.search !== searchParams.get('search')) params.delete('page'); if (next.page === '1') params.delete('page'); return params }, { replace: true })

  const canMutate = can(Permission.POST_UPDATE) || can(Permission.POST_DELETE)

  const columns: Array<Column<Post>> = [
    { key: 'title', header: 'Título', render: (post) => <div className="min-w-0"><p className="truncate font-medium text-foreground">{post.title}</p><p className="truncate text-[13px] text-muted">/{post.slug}</p></div> },
    { key: 'status', header: 'Status', render: (post) => <Badge variant={STATUS_BADGE[post.status]}>{POST_STATUS_LABELS[post.status]}</Badge> },
    { key: 'author', header: 'Autor', render: (post) => <span className="text-muted">{post.author?.name ?? '—'}</span> },
    { key: 'published_at', header: 'Publicado em', render: (post) => <span className="text-muted">{post.published_at ? formatDate(post.published_at) : '—'}</span> },
    ...(canMutate ? [{ key: 'actions', header: <span className="sr-only">Ações</span>, className: 'w-24 text-right', render: (post: Post) => (
      <div className="flex items-center justify-end gap-1">
        {can(Permission.POST_UPDATE) && <Button variant="ghost" size="sm" onClick={() => navigate(`/posts/${post.id}/edit`)} aria-label={`Editar ${post.title}`}><Pencil className="size-4" /></Button>}
        {can(Permission.POST_DELETE) && <Button variant="ghost" size="sm" onClick={() => setPostToDelete(post)} aria-label={`Excluir ${post.title}`} className="text-danger hover:bg-danger-soft hover:text-danger"><Trash2 className="size-4" /></Button>}
      </div>
    )} satisfies Column<Post>] : []),
  ]

  return (
    <Page>
      <PageHeader title="Posts" description="Gerencie o conteúdo do blog e páginas." breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Posts' }]} actions={<Can permission={Permission.POST_CREATE}><ButtonLink to="/posts/create"><Plus className="size-4" />Novo post</ButtonLink></Can>} />
      <PageContent>
        <FilterBar>
          <SearchInput placeholder="Buscar por título..." aria-label="Buscar posts" value={search} onChange={(event) => { setSearch(event.target.value); updateParams({ search: event.target.value, page: '' }) }} />
          <Select options={[{ value: '', label: 'Todos os status' }, ...POST_STATUS_OPTIONS]} value={statusFilter} onChange={(event) => updateParams({ status: event.target.value, page: '' })} className="w-48" aria-label="Filtrar por status" />
        </FilterBar>
        <DataTable caption="Lista de posts" columns={columns} rows={query.data?.data ?? []} rowKey={(post) => post.id} loading={query.isPending}
          emptyState={<EmptyState icon={FileText} title={debouncedSearch || statusFilter ? 'Nenhum resultado' : 'Nenhum post cadastrado'} description={debouncedSearch || statusFilter ? 'Tente ajustar os filtros.' : 'Comece criando o primeiro post.'} action={!debouncedSearch && !statusFilter ? <Can permission={Permission.POST_CREATE}><ButtonLink to="/posts/create"><Plus className="size-4" />Novo post</ButtonLink></Can> : undefined} />} />
        {query.data && <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: String(next) })} />}
      </PageContent>
      <ConfirmDialog open={postToDelete !== null} onClose={() => setPostToDelete(null)} onConfirm={() => { if (postToDelete) deletePost.mutate(postToDelete.id, { onSettled: () => setPostToDelete(null) }) }} loading={deletePost.isPending} title="Excluir post" description={<>Tem certeza que deseja excluir <strong>{postToDelete?.title}</strong>?</>} confirmLabel="Excluir" />
    </Page>
  )
}
