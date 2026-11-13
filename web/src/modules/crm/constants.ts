import type { StageOutcome } from './services/crm.service'

/** Cores permitidas para as etapas (mesma lista do back-end). */
export const STAGE_COLORS: Record<string, { label: string; dot: string; soft: string; border: string }> = {
  slate: { label: 'Cinza', dot: 'bg-slate-500', soft: 'bg-slate-500/10 text-slate-700 dark:text-slate-300', border: 'border-t-slate-500' },
  blue: { label: 'Azul', dot: 'bg-blue-500', soft: 'bg-blue-500/10 text-blue-700 dark:text-blue-300', border: 'border-t-blue-500' },
  cyan: { label: 'Ciano', dot: 'bg-cyan-500', soft: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300', border: 'border-t-cyan-500' },
  green: { label: 'Verde', dot: 'bg-green-500', soft: 'bg-green-500/10 text-green-700 dark:text-green-300', border: 'border-t-green-500' },
  amber: { label: 'Âmbar', dot: 'bg-amber-500', soft: 'bg-amber-500/10 text-amber-700 dark:text-amber-300', border: 'border-t-amber-500' },
  orange: { label: 'Laranja', dot: 'bg-orange-500', soft: 'bg-orange-500/10 text-orange-700 dark:text-orange-300', border: 'border-t-orange-500' },
  red: { label: 'Vermelho', dot: 'bg-red-500', soft: 'bg-red-500/10 text-red-700 dark:text-red-300', border: 'border-t-red-500' },
  pink: { label: 'Rosa', dot: 'bg-pink-500', soft: 'bg-pink-500/10 text-pink-700 dark:text-pink-300', border: 'border-t-pink-500' },
  violet: { label: 'Violeta', dot: 'bg-violet-500', soft: 'bg-violet-500/10 text-violet-700 dark:text-violet-300', border: 'border-t-violet-500' },
}

export function stageColor(color: string) {
  return STAGE_COLORS[color] ?? STAGE_COLORS.slate
}

export const OUTCOME_OPTIONS: Array<{ value: StageOutcome; label: string; hint: string }> = [
  { value: 'open', label: 'Em andamento', hint: 'Lead ainda em negociação.' },
  { value: 'qualified', label: 'Qualificado', hint: 'Conta como lead qualificado e entra na conversão offline do Google Ads.' },
  { value: 'won', label: 'Ganho', hint: 'Negócio fechado. Também entra na conversão offline.' },
  { value: 'lost', label: 'Perdido', hint: 'Negócio perdido.' },
]
