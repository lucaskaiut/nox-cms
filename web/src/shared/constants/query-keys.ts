import type { ListParams } from '@/shared/types/api'

export const queryKeys = {
  session: ['session'] as const,

  users: {
    all: ['users'] as const,
    list: (params: ListParams) => ['users', 'list', params] as const,
    detail: (id: string) => ['users', 'detail', id] as const,
  },

  roles: {
    all: ['roles'] as const,
    list: (params: ListParams) => ['roles', 'list', params] as const,
    detail: (id: number) => ['roles', 'detail', id] as const,
  },

  apiTokens: {
    all: ['api-tokens'] as const,
    list: () => ['api-tokens', 'list'] as const,
  },

  webhooks: {
    all: ['webhooks'] as const,
    list: () => ['webhooks', 'list'] as const,
    detail: (id: number) => ['webhooks', 'detail', id] as const,
    logs: (id: number) => ['webhooks', 'logs', id] as const,
    events: () => ['webhooks', 'events'] as const,
  },

  pipeline: {
    all: ['pipeline'] as const,
    stages: () => ['pipeline', 'stages'] as const,
    board: (search: string) => ['pipeline', 'board', search] as const,
    activities: (leadId: string) => ['pipeline', 'activities', leadId] as const,
  },

  whatsapp: {
    all: ['whatsapp'] as const,
    account: () => ['whatsapp', 'account'] as const,
    unread: () => ['whatsapp', 'unread'] as const,
    conversations: (params: { search?: string; unread?: boolean }) => ['whatsapp', 'conversations', params] as const,
    conversation: (id: string) => ['whatsapp', 'conversation', id] as const,
    messages: (id: string) => ['whatsapp', 'messages', id] as const,
    templates: () => ['whatsapp', 'templates'] as const,
  },

  leads: {
    all: ['leads'] as const,
    detail: (id: string) => ['leads', 'detail', id] as const,
    list: (params: ListParams & { status?: string; ref?: string }) => ['leads', 'list', params] as const,
  },
} as const
