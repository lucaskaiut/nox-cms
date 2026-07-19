import { z } from 'zod'

const statusValues = ['draft', 'review', 'scheduled', 'published', 'archived'] as const

export const postSchema = z.object({
  title: z.string().min(1, 'Informe o título'),
  slug: z.string(),
  excerpt: z.string(),
  content: z.string(),
  featured_image: z.string(),
  featured_image_alt: z.string(),
  status: z.enum(statusValues),
  meta_title: z.string(),
  meta_description: z.string(),
  canonical_url: z.string(),
  og_title: z.string(),
  og_description: z.string(),
  og_image: z.string(),
  schema_type: z.string(),
  allow_indexing: z.boolean(),
  include_in_sitemap: z.boolean(),
  is_featured: z.boolean(),
  published_at: z.string(),
  categories: z.array(z.number()),
  tags: z.array(z.string()),
})

export type PostFormValues = z.infer<typeof postSchema>
