import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/shared/stores/toast.store'
import { postsService, type PostFilters, type PostPayload } from '../services/posts.service'

export const postsQueryKeys = {
  all: ['posts'] as const,
  list: (filters: PostFilters) => ['posts', 'list', filters] as const,
  detail: (id: string) => ['posts', 'detail', id] as const,
}

export function usePostsQuery(filters: PostFilters) {
  return useQuery({ queryKey: postsQueryKeys.list(filters), queryFn: () => postsService.list(filters), placeholderData: keepPreviousData })
}

export function usePostQuery(id: string | undefined) {
  return useQuery({ queryKey: postsQueryKeys.detail(id ?? ''), queryFn: () => postsService.get(id!), enabled: !!id })
}

export function useCreatePost() {
  const qc = useQueryClient()

  return useMutation({ mutationFn: (p: PostPayload) => postsService.create(p), onSuccess: () => { qc.invalidateQueries({ queryKey: postsQueryKeys.all }); toast.success('Post criado') } })
}

export function useUpdatePost(id: string) {
  const qc = useQueryClient()

  return useMutation({ mutationFn: (p: PostPayload) => postsService.update(id, p), onSuccess: () => { qc.invalidateQueries({ queryKey: postsQueryKeys.all }); toast.success('Post atualizado') } })
}

export function useDeletePost() {
  const qc = useQueryClient()

  return useMutation({ mutationFn: (id: string) => postsService.remove(id), onSuccess: () => { qc.invalidateQueries({ queryKey: postsQueryKeys.all }); toast.success('Post removido') } })
}
