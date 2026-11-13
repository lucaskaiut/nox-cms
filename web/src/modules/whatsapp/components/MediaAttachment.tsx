import { useEffect, useState } from 'react'
import { Download, FileText } from 'lucide-react'
import { Spinner } from '@/shared/design-system'
import { whatsappService, type Message } from '../services/whatsapp.service'

/** Mídia recebida: baixada pelo CMS (a URL da Meta exige o token) e exibida via blob. */
export function MediaAttachment({ message }: { message: Message }) {
  const [url, setUrl] = useState<string | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let objectUrl: string | null = null
    let cancelled = false
    whatsappService
      .media(message.id)
      .then((blob) => {
        if (cancelled) return
        objectUrl = URL.createObjectURL(blob)
        setUrl(objectUrl)
      })
      .catch(() => !cancelled && setFailed(true))

    return () => {
      cancelled = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [message.id])

  if (failed) return <p className="text-xs italic opacity-80">Não foi possível carregar a mídia (a Meta guarda arquivos por 30 dias).</p>
  if (!url) return <Spinner className="my-2 size-5" />

  switch (message.type) {
    case 'image':
    case 'sticker':
      return (
        <a href={url} target="_blank" rel="noreferrer">
          <img src={url} alt={message.body ?? 'Imagem recebida'} className="max-h-72 rounded-md object-contain" />
        </a>
      )
    case 'audio':
      return <audio controls src={url} className="max-w-full" />
    case 'video':
      return <video controls src={url} className="max-h-72 max-w-full rounded-md" />
    default:
      return (
        <a href={url} download={message.media_filename ?? 'arquivo'} className="flex items-center gap-2 underline">
          <FileText className="size-4 shrink-0" aria-hidden="true" />
          <span className="truncate">{message.media_filename ?? 'Documento'}</span>
          <Download className="size-3.5 shrink-0" aria-hidden="true" />
        </a>
      )
  }
}
