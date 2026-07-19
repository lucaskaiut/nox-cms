import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/shared/stores/toast.store'
import { postsService, type PostFilters, type PostPayload } from '../services/posts.service'

export const postsQueryKeys = {
  all: ['posts'] as const,
  list: (filters: PostFilters) => ['posts', 'list', filters] as const,
  detail: (id: string) => ['posts', 'detail', id] as const,
}

export function usePostsQuery(filters: PostFilters) {
  return useQuery({
    queryKey: postsQueryKeys.list(filters),
    queryFn: () => postsService.list(filters),
    placeholderData: keepPreviousData,
  })
}

export function usePostQuery(id: string | undefined) {
  return useQuery({
    queryKey: postsQueryKeys.detail(id ?? ''),
    queryFn: () => postsService.get(id!),
    enabled: !!id,
  })
}

export function useCreatePost() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PostPayload) => postsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: postsQueryKeys.all })
      toast.success('Post criado', 'O post foi criado com sucesso.')
    },
  })
}

export function useUpdatePost(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PostPayload) => postsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: postsQueryKeys.all })
      toast.success('Post atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeletePost() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => postsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: postsQueryKeys.all })
      toast.success('Post removido', 'O post foi excluído com sucesso.')
    },
  })
}
