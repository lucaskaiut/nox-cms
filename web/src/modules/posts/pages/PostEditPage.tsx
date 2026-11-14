import { useNavigate, useParams } from 'react-router'
import { FileX } from 'lucide-react'
import { ButtonLink, Card, CardContent, EmptyState, Page, PageContent, PageHeader, Skeleton } from '@/shared/design-system'
import { toDateTimeInput } from '@/shared/utils/format'
import { PostInsightsPanel } from '../components/PostInsightsPanel'
import { PostForm } from '../forms/PostForm'
import { usePostQuery, useUpdatePost } from '../hooks/usePosts'

export default function PostEditPage() {
  const { id } = useParams<{ id: string }>(); const nav = useNavigate(); const q = usePostQuery(id); const um = useUpdatePost(id ?? '')
  return (<Page><PageHeader title="Editar post" breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Posts', to: '/posts' }, { label: 'Editar' }]} /><PageContent>
    {q.isPending && <div className="space-y-5">{Array.from({ length: 3 }).map((_, i) => <Card key={i}><CardContent><Skeleton className="h-10" /><Skeleton className="mt-3 h-40" /></CardContent></Card>)}</div>}
    {q.isError && <Card><EmptyState icon={FileX} title="Post não encontrado" action={<ButtonLink to="/posts" variant="secondary">Voltar</ButtonLink>} /></Card>}
    {q.data && <div className="grid gap-6 xl:grid-cols-[1fr_22rem] xl:items-start"><div className="min-w-0"><PostForm mode="edit" defaultValues={{ title: q.data.title, slug: q.data.slug, excerpt: q.data.excerpt ?? '', content: q.data.content ?? '', featured_image: q.data.featured_image ?? '', featured_image_alt: q.data.featured_image_alt ?? '', status: q.data.status, meta_title: q.data.meta_title ?? '', meta_description: q.data.meta_description ?? '', canonical_url: q.data.canonical_url ?? '', og_title: q.data.og_title ?? '', og_description: q.data.og_description ?? '', og_image: q.data.og_image ?? '', schema_type: q.data.schema_type ?? 'Article', allow_indexing: q.data.allow_indexing, include_in_sitemap: q.data.include_in_sitemap, is_featured: q.data.is_featured, published_at: toDateTimeInput(q.data.published_at), categories: q.data.categories?.map((c) => c.id) ?? [], tags: q.data.tags?.map((t) => t.name) ?? [], cta_offer: q.data.cta_offer ?? '', cta_text: q.data.cta_text ?? '', cta_url: q.data.cta_url ?? '' }} submitting={um.isPending} onSubmit={async (p) => { await um.mutateAsync(p); nav('/posts') }} /></div><PostInsightsPanel postId={q.data.id} /></div>}
  </PageContent></Page>)
}
