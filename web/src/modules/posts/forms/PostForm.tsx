import { useForm, useFormContext, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Field,
  Form,
  ImageUploader,
  RichTextEditor,
  Section,
  SelectField,
  SlugField,
  SwitchField,
  TagSelector,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { postSchema, type PostFormValues } from '../schemas/post.schema'
import { POST_STATUS_OPTIONS, type PostPayload } from '../services/posts.service'

interface PostFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<PostFormValues>
  submitting: boolean
  onSubmit: (payload: PostPayload) => Promise<unknown>
}

function SeoSection() {
  return (
    <Section title="SEO" description="Metadados para mecanismos de busca.">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="meta_title" label="Meta título" hint="Até 160 caracteres" />
        <TextField name="canonical_url" label="URL canônica" placeholder="https://" />
        <TextareaField name="meta_description" label="Meta descrição" hint="Até 320 caracteres" rows={3} className="sm:col-span-2" />
        <TextField name="meta_keywords" label="Palavras-chave" hint="Separadas por vírgula" className="sm:col-span-2" />
      </div>
    </Section>
  )
}

function OpenGraphSection() {
  return (
    <Section title="Open Graph" description="Metadados para compartilhamento em redes sociais.">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="og_title" label="Título OG" hint="Até 160 caracteres" />
        <TextField name="og_image" label="Imagem OG (URL)" placeholder="https://" />
        <TextareaField name="og_description" label="Descrição OG" hint="Até 320 caracteres" rows={2} className="sm:col-span-2" />
      </div>
    </Section>
  )
}

function FeaturedImageSection() {
  const { setValue, watch } = useFormContext<PostFormValues>()
  const image = watch('featured_image')
  const alt = watch('featured_image_alt')

  return (
    <Section title="Imagem destacada">
      <ImageUploader
        value={image ? { url: image, alt } : null}
        onChange={(value) => {
          setValue('featured_image', value?.url ?? '', { shouldValidate: true })

          if (value?.alt !== undefined) {
            setValue('featured_image_alt', value.alt, { shouldValidate: true })
          }
        }}
      />
      <TextField name="featured_image_alt" label="Texto ALT" hint="Descrição da imagem para acessibilidade" className="mt-4" />
    </Section>
  )
}

export function PostForm({ mode, defaultValues, submitting, onSubmit }: PostFormProps) {
  const form = useForm<PostFormValues>({
    resolver: zodResolver(postSchema),
    defaultValues: {
      title: '',
      slug: '',
      excerpt: '',
      content: '',
      featured_image: '',
      featured_image_alt: '',
      status: 'draft',
      meta_title: '',
      meta_description: '',
      meta_keywords: '',
      canonical_url: '',
      og_title: '',
      og_description: '',
      og_image: '',
      schema_type: 'Article',
      allow_indexing: true,
      include_in_sitemap: true,
      is_featured: false,
      published_at: '',
      categories: [],
      tags: [],
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: PostFormValues) => {
    const payload: PostPayload = {
      title: values.title,
      slug: values.slug || null,
      excerpt: values.excerpt || null,
      content: values.content || null,
      featured_image: values.featured_image || null,
      featured_image_alt: values.featured_image_alt || null,
      status: values.status,
      meta_title: values.meta_title || null,
      meta_description: values.meta_description || null,
      meta_keywords: values.meta_keywords || null,
      canonical_url: values.canonical_url || null,
      og_title: values.og_title || null,
      og_description: values.og_description || null,
      og_image: values.og_image || null,
      schema_type: values.schema_type || null,
      allow_indexing: values.allow_indexing,
      include_in_sitemap: values.include_in_sitemap,
      is_featured: values.is_featured,
      published_at: values.published_at || null,
      categories: values.categories,
      tags: values.tags,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Form form={form} onSubmit={handleSubmit} className="space-y-8">
      <Card>
        <CardContent className="space-y-5">
          <Section title="Conteúdo">
            <div className="grid gap-4">
              <TextField name="title" label="Título" placeholder="Título do post" required />
              <SlugField<PostFormValues> name="slug" sourceField="title" />
              <TextareaField name="excerpt" label="Resumo" placeholder="Breve descrição do post..." rows={3} />
              <Controller
                name="content"
                control={form.control}
                render={({ field, fieldState }) => (
                  <RichTextEditor
                    label="Conteúdo"
                    value={field.value}
                    onChange={field.onChange}
                    error={fieldState.error?.message}
                  />
                )}
              />
            </div>
          </Section>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <FeaturedImageSection />
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <SeoSection />
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <OpenGraphSection />
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-5">
          <Section title="Publicação">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <SelectField name="status" label="Status" options={POST_STATUS_OPTIONS} required />
              <TextField name="published_at" label="Publicado em" type="datetime-local" hint="Agende ou registre a data" />
              <TextField name="schema_type" label="Schema type" placeholder="Article" hint="Article, BlogPosting, TechArticle" />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
              <SwitchField name="is_featured" label="Destaque" hint="Exibir na página inicial" />
              <SwitchField name="allow_indexing" label="Permitir indexação" hint="Motores de busca podem indexar" />
              <SwitchField name="include_in_sitemap" label="Incluir no sitemap" hint="Disponível para sitemap.xml" />
            </div>
          </Section>

          <Section title="Tags e categorias">
            <div className="grid gap-4 sm:grid-cols-2">
              <Controller
                name="tags"
                control={form.control}
                render={({ field, fieldState }) => (
                  <TagSelector
                    label="Tags"
                    hint="Pressione Enter para adicionar"
                    value={field.value}
                    onChange={field.onChange}
                    error={fieldState.error?.message}
                  />
                )}
              />
              <Field label="Categorias" hint="Selecione as categorias do post">
                <p className="text-[13px] text-muted">Seleção de categorias disponível em breve.</p>
              </Field>
            </div>
          </Section>
        </CardContent>
      </Card>

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <ButtonLink to="/posts" variant="secondary">
          Cancelar
        </ButtonLink>
        <Button type="submit" loading={submitting}>
          {mode === 'create' ? 'Criar post' : 'Salvar alterações'}
        </Button>
      </div>
    </Form>
  )
}
