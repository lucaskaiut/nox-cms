export const Permission = {
  USER_CREATE: 'user.create',
  USER_READ: 'user.read',
  USER_UPDATE: 'user.update',
  USER_DELETE: 'user.delete',

  TENANT_READ: 'tenant.read',
  TENANT_UPDATE: 'tenant.update',

  ROLE_CREATE: 'role.create',
  ROLE_READ: 'role.read',
  ROLE_UPDATE: 'role.update',
  ROLE_DELETE: 'role.delete',

  API_TOKEN_CREATE: 'api-token.create',
  API_TOKEN_READ: 'api-token.read',
  API_TOKEN_UPDATE: 'api-token.update',
  API_TOKEN_DELETE: 'api-token.delete',

  WEBHOOK_CREATE: 'webhook.create',
  WEBHOOK_READ: 'webhook.read',
  WEBHOOK_UPDATE: 'webhook.update',
  WEBHOOK_DELETE: 'webhook.delete',

  POST_CREATE: 'post.create',
  POST_READ: 'post.read',
  POST_UPDATE: 'post.update',
  POST_DELETE: 'post.delete',
  POST_PUBLISH: 'post.publish',

  AI_PUBLISH: 'ai.publish',
  AI_READ: 'ai.read',

  LEAD_CREATE: 'lead.create',
  LEAD_READ: 'lead.read',
  LEAD_UPDATE: 'lead.update',
} as const

export type Permission = (typeof Permission)[keyof typeof Permission]

export interface PermissionGroup {
  label: string
  permissions: Array<{ value: Permission; label: string }>
}

export const PERMISSION_GROUPS: PermissionGroup[] = [
  {
    label: 'Usuários',
    permissions: [
      { value: Permission.USER_READ, label: 'Visualizar usuários' },
      { value: Permission.USER_CREATE, label: 'Criar usuários' },
      { value: Permission.USER_UPDATE, label: 'Editar usuários' },
      { value: Permission.USER_DELETE, label: 'Remover usuários' },
    ],
  },
  {
    label: 'Organização',
    permissions: [
      { value: Permission.TENANT_READ, label: 'Visualizar dados da organização' },
      { value: Permission.TENANT_UPDATE, label: 'Editar dados da organização' },
    ],
  },
  {
    label: 'Perfis de acesso',
    permissions: [
      { value: Permission.ROLE_READ, label: 'Visualizar perfis' },
      { value: Permission.ROLE_CREATE, label: 'Criar perfis' },
      { value: Permission.ROLE_UPDATE, label: 'Editar perfis' },
      { value: Permission.ROLE_DELETE, label: 'Remover perfis' },
    ],
  },
  {
    label: 'Tokens de API',
    permissions: [
      { value: Permission.API_TOKEN_READ, label: 'Visualizar tokens' },
      { value: Permission.API_TOKEN_CREATE, label: 'Criar tokens' },
      { value: Permission.API_TOKEN_UPDATE, label: 'Editar escopos de tokens' },
      { value: Permission.API_TOKEN_DELETE, label: 'Revogar tokens' },
    ],
  },
  {
    label: 'Webhooks',
    permissions: [
      { value: Permission.WEBHOOK_READ, label: 'Visualizar webhooks' },
      { value: Permission.WEBHOOK_CREATE, label: 'Criar webhooks' },
      { value: Permission.WEBHOOK_UPDATE, label: 'Editar webhooks' },
      { value: Permission.WEBHOOK_DELETE, label: 'Remover webhooks' },
    ],
  },
  {
    label: 'Conteúdo',
    permissions: [
      { value: Permission.POST_READ, label: 'Visualizar posts' },
      { value: Permission.POST_CREATE, label: 'Criar posts' },
      { value: Permission.POST_UPDATE, label: 'Editar posts' },
      { value: Permission.POST_DELETE, label: 'Remover posts' },
      { value: Permission.POST_PUBLISH, label: 'Publicar posts' },
    ],
  },
  {
    label: 'AI Publisher',
    permissions: [
      { value: Permission.AI_READ, label: 'Consultar AI Publisher' },
      { value: Permission.AI_PUBLISH, label: 'Publicar via IA' },
    ],
  },
  {
    label: 'Leads',
    permissions: [
      { value: Permission.LEAD_READ, label: 'Visualizar leads' },
      { value: Permission.LEAD_CREATE, label: 'Registrar leads (site)' },
      { value: Permission.LEAD_UPDATE, label: 'Qualificar leads' },
    ],
  },
]
