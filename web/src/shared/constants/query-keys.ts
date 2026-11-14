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

  tasks: {
    all: ['tasks'] as const,
    list: (params: { scope?: string; status?: string; lead?: string }) => ['tasks', 'list', params] as const,
    stale: () => ['tasks', 'stale'] as const,
    assignees: () => ['tasks', 'assignees'] as const,
  },

  crm: {
    tags: () => ['crm', 'tags'] as const,
    lostReasons: () => ['crm', 'lost-reasons'] as const,
    settings: () => ['crm', 'settings'] as const,
  },

  nurture: {
    all: ['nurture'] as const,
    sequences: () => ['nurture', 'sequences'] as const,
    sequence: (id: string) => ['nurture', 'sequence', id] as const,
    sequenceEnrollments: (id: string) => ['nurture', 'sequence-enrollments', id] as const,
    leadEnrollments: (leadId: string) => ['nurture', 'lead', leadId] as const,
  },

  reports: {
    all: ['reports'] as const,
    funnel: (params: { from?: string; to?: string }) => ['reports', 'funnel', params] as const,
    content: (params: { from?: string; to?: string }) => ['reports', 'content', params] as const,
    stalePosts: () => ['reports', 'stale-posts'] as const,
    postPerformance: (id: string) => ['reports', 'post', id] as const,
    linkSuggestions: (id: string) => ['reports', 'links', id] as const,
    adSpends: () => ['reports', 'ad-spends'] as const,
  },

  integrations: {
    googleAds: () => ['integrations', 'google-ads'] as const,
    googleAdsActions: () => ['integrations', 'google-ads', 'actions'] as const,
    googleAdsUploads: (status: string) => ['integrations', 'google-ads', 'uploads', status] as const,
    searchConsole: () => ['integrations', 'search-console'] as const,
  },

  leads: {
    all: ['leads'] as const,
    detail: (id: string) => ['leads', 'detail', id] as const,
    list: (params: ListParams & { status?: string; ref?: string; tag?: string; post?: string }) => ['leads', 'list', params] as const,
  },
} as const
