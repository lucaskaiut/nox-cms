import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { PostForm } from '../forms/PostForm'
import { useCreatePost } from '../hooks/usePosts'

export default function PostCreatePage() {
  const navigate = useNavigate()
  const createPost = useCreatePost()

  return (
    <Page>
      <PageHeader title="Novo post" description="Crie um novo conteúdo para o blog." breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Posts', to: '/posts' }, { label: 'Novo post' }]} />
      <PageContent>
        <PostForm mode="create" submitting={createPost.isPending} onSubmit={async (payload) => { await createPost.mutateAsync(payload); navigate('/posts') }} />
      </PageContent>
    </Page>
  )
}
