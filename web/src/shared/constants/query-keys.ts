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
} as const
