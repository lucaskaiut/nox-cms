import { Suspense } from 'react'
import { Outlet } from 'react-router'
import { BarChart3, CalendarCheck, Columns3, LayoutDashboard, FileText, FolderTree, KeyRound, ListOrdered, LogOut, MailPlus, Menu, MessageCircle, MessageSquareText, MessagesSquare, Plug, Receipt, Settings, ShieldCheck, SlidersHorizontal, Users, Webhook, Zap } from 'lucide-react'
import { useSessionStore } from '@/shared/stores/session.store'
import { useUiStore } from '@/shared/stores/ui.store'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useLogout } from '@/modules/auth/hooks/useAuth'
import { useUnreadQuery } from '@/modules/whatsapp/hooks/useWhatsApp'
import {
  Avatar,
  Container,
  Dropdown,
  DropdownItem,
  DropdownSeparator,
  Loading,
  Sidebar,
  SidebarGroup,
  SidebarItem,
  ThemeToggle,
  Topbar,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'

function Brand() {
  const tenant = useSessionStore((state) => state.tenant)

  return (
    <div className="flex items-center gap-2.5 px-1">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-card">
        <Zap className="size-4.5" aria-hidden="true" />
      </span>
      <span className="min-w-0">
        <span className="block text-sm leading-tight font-semibold text-foreground">Nox CMS</span>
        <span className="block truncate text-xs text-muted">{tenant?.name}</span>
      </span>
    </div>
  )
}

function SidebarNavigation({ onNavigate }: { onNavigate?: () => void }) {
  const { can } = usePermissions()
  const unread = useUnreadQuery()

  return (
    <Sidebar header={<Brand />}>
      <SidebarGroup label="Geral">
        <SidebarItem to="/dashboard" icon={LayoutDashboard} label="Dashboard" onNavigate={onNavigate} />
      </SidebarGroup>

      <SidebarGroup label="Gestão">
        {can(Permission.USER_READ) && (
          <SidebarItem to="/users" icon={Users} label="Usuários" onNavigate={onNavigate} />
        )}
        {can(Permission.ROLE_READ) && (
          <SidebarItem to="/roles" icon={ShieldCheck} label="Perfis de acesso" onNavigate={onNavigate} />
        )}
        {can(Permission.API_TOKEN_READ) && (
          <SidebarItem to="/api-tokens" icon={KeyRound} label="Tokens de API" onNavigate={onNavigate} />
        )}
        {can(Permission.WEBHOOK_READ) && (
          <SidebarItem to="/webhooks" icon={Webhook} label="Webhooks" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

      {(can(Permission.LEAD_READ) || can(Permission.WHATSAPP_READ)) && (
        <SidebarGroup label="Comercial">
          {can(Permission.LEAD_READ) && (
            <SidebarItem to="/crm" icon={Columns3} label="Funil" onNavigate={onNavigate} />
          )}
          {can(Permission.LEAD_READ) && (
            <SidebarItem to="/tarefas" icon={CalendarCheck} label="Tarefas" onNavigate={onNavigate} />
          )}
          {can(Permission.WHATSAPP_READ) && (
            <SidebarItem
              to="/conversas"
              icon={MessagesSquare}
              label="Conversas"
              badge={unread.data?.conversations}
              onNavigate={onNavigate}
            />
          )}
          {can(Permission.LEAD_READ) && (
            <SidebarItem to="/leads" icon={MessageSquareText} label="Leads" onNavigate={onNavigate} />
          )}
          {can(Permission.NURTURE_MANAGE) && (
            <SidebarItem to="/nutricao" icon={MailPlus} label="Nutrição" onNavigate={onNavigate} />
          )}
        </SidebarGroup>
      )}

      {can(Permission.REPORT_READ) && (
        <SidebarGroup label="Relatórios">
          <SidebarItem to="/relatorios/conteudo" icon={BarChart3} label="Conteúdo que converte" onNavigate={onNavigate} />
        </SidebarGroup>
      )}

      {(can(Permission.PIPELINE_MANAGE) || can(Permission.WHATSAPP_MANAGE) || can(Permission.INTEGRATION_MANAGE)) && (
        <SidebarGroup label="Configurações">
          {can(Permission.PIPELINE_MANAGE) && (
            <SidebarItem to="/crm/etapas" icon={ListOrdered} label="Etapas do funil" onNavigate={onNavigate} />
          )}
          {can(Permission.PIPELINE_MANAGE) && (
            <SidebarItem to="/settings/crm" icon={SlidersHorizontal} label="CRM e alertas" onNavigate={onNavigate} />
          )}
          {can(Permission.INTEGRATION_MANAGE) && (
            <SidebarItem to="/settings/integracoes" icon={Plug} label="Integrações Google" onNavigate={onNavigate} />
          )}
          {can(Permission.INTEGRATION_MANAGE) && (
            <SidebarItem to="/settings/investimento" icon={Receipt} label="Investimento em anúncios" onNavigate={onNavigate} />
          )}
          {can(Permission.WHATSAPP_MANAGE) && (
            <SidebarItem to="/settings/whatsapp" icon={MessageCircle} label="WhatsApp" onNavigate={onNavigate} />
          )}
        </SidebarGroup>
      )}

      {can(Permission.POST_READ) && (
        <SidebarGroup label="Conteúdo">
          <SidebarItem to="/posts" icon={FileText} label="Posts" onNavigate={onNavigate} />
          <SidebarItem to="/categories" icon={FolderTree} label="Categorias" onNavigate={onNavigate} />
        </SidebarGroup>
      )}

      {can(Permission.AI_READ) && (
        <SidebarGroup label="IA">
          <SidebarItem to="/settings/editorial" icon={Settings} label="Editorial" onNavigate={onNavigate} />
        </SidebarGroup>
      )}
    </Sidebar>
  )
}

function UserMenu() {
  const user = useSessionStore((state) => state.user)
  const logout = useLogout()

  if (!user) return null

  return (
    <Dropdown
      label="Menu do usuário"
      trigger={
        <span className="flex items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-surface-2">
          <Avatar name={user.name} size="sm" />
          <span className="hidden text-left sm:block">
            <span className="block max-w-36 truncate text-[13px] leading-tight font-medium text-foreground">
              {user.name}
            </span>
            <span className="block max-w-36 truncate text-xs text-muted">{user.email}</span>
          </span>
        </span>
      }
    >
      <div className="px-3 py-2 sm:hidden">
        <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
        <p className="truncate text-xs text-muted">{user.email}</p>
      </div>
      <DropdownSeparator />
      <DropdownItem icon={LogOut} danger onSelect={() => logout.mutate()}>
        Sair da conta
      </DropdownItem>
    </Dropdown>
  )
}

export function AppLayout() {
  const sidebarOpen = useUiStore((state) => state.sidebarOpen)
  const closeSidebar = useUiStore((state) => state.closeSidebar)
  const openSidebar = useUiStore((state) => state.openSidebar)

  return (
    <div className="flex h-dvh overflow-hidden">
      <div className="z-20 hidden shrink-0 shadow-card lg:block">
        <SidebarNavigation />
      </div>

      {sidebarOpen && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-overlay lg:hidden"
          onClick={closeSidebar}
          aria-hidden="true"
        />
      )}
      <div
        className={cn(
          'fixed inset-y-0 left-0 z-50 shadow-pop transition-transform duration-200 lg:hidden',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <SidebarNavigation onNavigate={closeSidebar} />
      </div>

      <div className="flex min-w-0 flex-1 flex-col overflow-y-auto">
        <Topbar>
          <button
            type="button"
            onClick={openSidebar}
            aria-label="Abrir menu"
            className="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:hidden"
          >
            <Menu className="size-5" />
          </button>
          <div className="ml-auto flex items-center gap-1.5">
            <ThemeToggle />
            <UserMenu />
          </div>
        </Topbar>

        <main className="flex-1">
          <Container className="pt-2">
            <Suspense fallback={<Loading />}>
              <Outlet />
            </Suspense>
          </Container>
        </main>
      </div>
    </div>
  )
}
