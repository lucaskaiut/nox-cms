import { useNavigate, useParams } from 'react-router'
import { FileX } from 'lucide-react'
import { ButtonLink, Card, CardContent, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { PostForm } from '../forms/PostForm'
import { usePostQuery, useUpdatePost } from '../hooks/usePosts'

export default function PostEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const query = usePostQuery(id)
  const updatePost = useUpdatePost(id ?? '')

  return (
    <Page>
      <PageHeader title="Editar post" description={query.data ? `Editando: ${query.data.title}` : undefined} breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Posts', to: '/posts' }, { label: 'Editar' }]} />
      <PageContent>
        {query.isPending && <div className="space-y-5">{Array.from({ length: 3 }).map((_, i) => <Card key={i}><CardContent><Skeleton className="h-10 sm:col-span-2" /><Skeleton className="mt-3 h-40 sm:col-span-2" /></CardContent></Card>)}</div>}
        {query.isError && <Card><EmptyState icon={FileX} title="Post não encontrado" description="O post pode ter sido removido ou você não possui acesso." action={<ButtonLink to="/posts" variant="secondary">Voltar</ButtonLink>} /></Card>}
        {query.data && (
          <PostForm mode="edit" defaultValues={{
            title: query.data.title, slug: query.data.slug, excerpt: query.data.excerpt ?? '', content: query.data.content ?? '',
            featured_image: query.data.featured_image ?? '', featured_image_alt: query.data.featured_image_alt ?? '',
            status: query.data.status, meta_title: query.data.meta_title ?? '', meta_description: query.data.meta_description ?? '',
            canonical_url: query.data.canonical_url ?? '', og_title: query.data.og_title ?? '',
            og_description: query.data.og_description ?? '', og_image: query.data.og_image ?? '',
            schema_type: query.data.schema_type ?? 'Article', allow_indexing: query.data.allow_indexing,
            include_in_sitemap: query.data.include_in_sitemap, is_featured: query.data.is_featured,
            published_at: query.data.published_at ?? '', categories: query.data.categories?.map((c) => c.id) ?? [],
            tags: query.data.tags?.map((t) => t.name) ?? [],
          }} submitting={updatePost.isPending} onSubmit={async (payload) => { await updatePost.mutateAsync(payload); navigate('/posts') }} />
        )}
      </PageContent>
    </Page>
  )
}
