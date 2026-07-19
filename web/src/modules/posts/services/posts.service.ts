import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'

export interface Post {
  id: string; title: string; slug: string
  excerpt: string | null; content: string | null
  featured_image: string | null; featured_image_alt: string | null
  status: PostStatus
  meta_title: string | null; meta_description: string | null
  canonical_url: string | null
  og_title: string | null; og_description: string | null; og_image: string | null
  schema_type: string | null
  allow_indexing: boolean; include_in_sitemap: boolean; is_featured: boolean
  published_at: string | null
  author: { id: string; name: string } | null
  categories: Array<{ id: number; name: string; slug: string }>
  tags: Array<{ id: number; name: string; slug: string }>
  created_at: string | null; updated_at: string | null
}

export interface Category {
  id: number; name: string; slug: string; description: string | null; parent_id: number | null
  children?: Category[]
}

export type PostStatus = 'draft' | 'review' | 'scheduled' | 'published' | 'archived'

export const POST_STATUS_LABELS: Record<PostStatus, string> = {
  draft: 'Rascunho', review: 'Em revisão', scheduled: 'Agendado', published: 'Publicado', archived: 'Arquivado',
}

export const POST_STATUS_OPTIONS = Object.entries(POST_STATUS_LABELS).map(([value, label]) => ({ value, label }))

export interface PostPayload {
  title: string; slug?: string | null; excerpt?: string | null; content?: string | null
  featured_image?: string | null; featured_image_alt?: string | null
  status: PostStatus
  meta_title?: string | null; meta_description?: string | null
  canonical_url?: string | null
  og_title?: string | null; og_description?: string | null; og_image?: string | null
  schema_type?: string | null
  allow_indexing?: boolean; include_in_sitemap?: boolean; is_featured?: boolean
  published_at?: string | null
  categories?: number[]; tags?: string[]
}

export interface PostFilters extends ListParams {
  status?: string; category?: number; search?: string
}

export const postsService = {
  async list(filters: PostFilters) { return (await http.get<PaginatedResponse<Post>>('/posts', { params: filters })).data },
  async get(id: string) { return (await http.get<ApiResponse<Post>>(`/posts/${id}`)).data.data },
  async create(payload: PostPayload) { return (await http.post<ApiResponse<Post>>('/posts', payload)).data.data },
  async update(id: string, payload: PostPayload) { return (await http.put<ApiResponse<Post>>(`/posts/${id}`, payload)).data.data },
  async remove(id: string) { await http.delete(`/posts/${id}`) },
}

export const categoriesService = {
  async list() { return (await http.get<ApiResponse<Category[]>>('/categories')).data.data },
  async create(payload: { name: string; slug?: string; description?: string; parent_id?: number | null }) { return (await http.post<ApiResponse<Category>>('/categories', payload)).data.data },
  async update(id: number, payload: { name?: string; slug?: string; description?: string; parent_id?: number | null }) { return (await http.put<ApiResponse<Category>>(`/categories/${id}`, payload)).data.data },
  async remove(id: number) { await http.delete(`/categories/${id}`) },
}
