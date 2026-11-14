<?php

namespace App\Modules\Nurture\Services;

use App\Modules\Crm\Models\LeadActivity;
use App\Modules\Crm\Models\LeadTag;
use App\Modules\Crm\Services\LeadMailer;
use App\Modules\Crm\Services\PipelineService;
use App\Modules\Lead\Enums\LeadStatus;
use App\Modules\Lead\Models\Lead;
use App\Modules\Nurture\Models\NurtureEnrollment;
use App\Modules\Nurture\Models\NurtureSequence;
use App\Modules\Nurture\Models\NurtureStep;
use App\Modules\Post\Models\Post;
use App\Modules\User\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sequências de e-mail: inscrição (manual, por tag, reativação de perdidos),
 * envio dos passos no tempo certo e interrupção quando o lead muda de situação.
 */
class NurtureService
{
    /** Reativação não pega perdidos muito antigos (evita disparo em massa ao criar a sequência). */
    private const LOST_LOOKBACK_DAYS = 30;

    public function __construct(
        private readonly LeadMailer $mailer,
        private readonly PipelineService $pipeline,
    ) {}

    public function save(?NurtureSequence $sequence, array $data): NurtureSequence
    {
        return DB::transaction(function () use ($sequence, $data) {
            $sequence ??= new NurtureSequence;
            $sequence->fill(Arr::only($data, ['name', 'trigger', 'trigger_delay_days', 'is_active']));
            if (array_key_exists('trigger_tag_id', $data)) {
                $sequence->trigger_tag_id = $data['trigger_tag_id'] ? LeadTag::query()->where('uuid', $data['trigger_tag_id'])->value('id') : null;
            }
            if ($sequence->trigger !== NurtureSequence::TRIGGER_TAG) {
                $sequence->trigger_tag_id = null;
            }
            $sequence->save();

            if (array_key_exists('steps', $data)) {
                $this->syncSteps($sequence, $data['steps']);
            }

            return $sequence->load(['steps.post', 'triggerTag']);
        });
    }

    private function syncSteps(NurtureSequence $sequence, array $steps): void
    {
        $keep = [];
        foreach (array_values($steps) as $position => $step) {
            $model = filled($step['id'] ?? null)
                ? $sequence->steps()->where('uuid', $step['id'])->first() ?? new NurtureStep
                : new NurtureStep;
            $model->fill([
                'sequence_id' => $sequence->id,
                'position' => $position,
                'delay_days' => (int) $step['delay_days'],
                'subject' => $step['subject'],
                'body' => $step['body'],
                'post_id' => filled($step['post_id'] ?? null) ? Post::query()->where('uuid', $step['post_id'])->value('id') : null,
            ])->save();
            $keep[] = $model->id;
        }
        $sequence->steps()->whereNotIn('id', $keep)->delete();
    }

    public function enroll(Lead $lead, NurtureSequence $sequence, bool $manual = false, ?User $user = null): ?NurtureEnrollment
    {
        if (! $lead->canReceiveEmail()) {
            if ($manual) {
                throw ValidationException::withMessages(['sequence_id' => $lead->email_opt_out_at ? 'O lead se descadastrou dos e-mails.' : 'O lead não tem e-mail.']);
            }

            return null;
        }

        $first = $sequence->steps()->first();
        if (! $first) {
            if ($manual) throw ValidationException::withMessages(['sequence_id' => 'A sequência ainda não tem e-mails.']);

            return null;
        }

        $enrollment = NurtureEnrollment::query()->forTenant($lead->tenant_id)
            ->firstOrNew(['sequence_id' => $sequence->id, 'lead_id' => $lead->id]);

        if ($enrollment->exists && ($enrollment->status === NurtureEnrollment::ACTIVE || ! $manual)) {
            return $manual ? $enrollment : null;
        }

        $enrollment->fill([
            'tenant_id' => $lead->tenant_id, 'next_step' => 0, 'status' => NurtureEnrollment::ACTIVE,
            'next_run_at' => now()->addDays($first->delay_days), 'stopped_reason' => null,
        ])->save();

        $this->pipeline->log($lead, LeadActivity::NOTE, "Inscrito na sequência \"{$sequence->name}\"", ['kind' => 'nurture'], $user);

        return $enrollment->load('sequence');
    }

    public function stop(NurtureEnrollment $enrollment, string $reason): void
    {
        if ($enrollment->status !== NurtureEnrollment::ACTIVE) {
            return;
        }
        $enrollment->forceFill(['status' => NurtureEnrollment::STOPPED, 'stopped_reason' => $reason, 'next_run_at' => null])->save();
    }

    /** Envia os passos vencidos. Retorna quantos e-mails saíram. */
    public function processDue(int $limit = 200): int
    {
        $sent = 0;
        NurtureEnrollment::query()->with(['sequence.steps.post', 'lead'])
            ->where('status', NurtureEnrollment::ACTIVE)->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')->limit($limit)->get()
            ->each(function (NurtureEnrollment $enrollment) use (&$sent) {
                try {
                    $sent += (int) $this->advance($enrollment);
                } catch (Throwable $e) {
                    report($e);
                    // Tenta de novo mais tarde sem travar a fila.
                    $enrollment->forceFill(['next_run_at' => now()->addHour()])->save();
                }
            });

        return $sent;
    }

    private function advance(NurtureEnrollment $enrollment): bool
    {
        $sequence = $enrollment->sequence;
        $lead = $enrollment->lead;
        if (! $sequence || ! $lead) {
            $enrollment->delete();

            return false;
        }
        if (! $sequence->is_active) {
            return false; // pausada: retoma quando reativar
        }
        if ($reason = $this->stopReason($lead, $sequence)) {
            $this->stop($enrollment, $reason);

            return false;
        }

        $steps = $sequence->steps->values();
        $step = $steps[$enrollment->next_step] ?? null;
        if (! $step) {
            $enrollment->forceFill(['status' => NurtureEnrollment::COMPLETED, 'next_run_at' => null])->save();

            return false;
        }

        $this->mailer->send($lead, $step->subject, $step->body, $step->post, [
            'kind' => 'nurture', 'sequence' => $sequence->name, 'utm_campaign' => 'nutricao-'.$sequence->id,
        ]);

        $next = $steps[$enrollment->next_step + 1] ?? null;
        $enrollment->forceFill([
            'next_step' => $enrollment->next_step + 1,
            'last_sent_at' => now(),
            'next_run_at' => $next ? now()->addDays($next->delay_days) : null,
            'status' => $next ? NurtureEnrollment::ACTIVE : NurtureEnrollment::COMPLETED,
        ])->save();

        return true;
    }

    public function stopReason(Lead $lead, NurtureSequence $sequence): ?string
    {
        if (! $lead->canReceiveEmail()) {
            return $lead->email_opt_out_at ? 'Descadastrado' : 'Sem e-mail';
        }
        if ($sequence->trigger === NurtureSequence::TRIGGER_LOST) {
            return $lead->status === LeadStatus::LOST ? null : 'Lead reaberto';
        }

        return match ($lead->status) {
            LeadStatus::WON => 'Lead fechado',
            LeadStatus::LOST => 'Lead perdido',
            default => null,
        };
    }

    /** Para as inscrições que não fazem mais sentido depois da mudança de status. */
    public function onStatusChanged(Lead $lead): void
    {
        $lead->enrollments()->with('sequence')->where('status', NurtureEnrollment::ACTIVE)->get()
            ->each(function (NurtureEnrollment $e) use ($lead) {
                if ($e->sequence && ($reason = $this->stopReason($lead, $e->sequence))) {
                    $this->stop($e, $reason);
                }
            });
    }

    /** @param list<int> $tagIds */
    public function onTagged(Lead $lead, array $tagIds): void
    {
        NurtureSequence::query()->forTenant($lead->tenant_id)->where('is_active', true)
            ->where('trigger', NurtureSequence::TRIGGER_TAG)->whereIn('trigger_tag_id', $tagIds)->get()
            ->each(fn (NurtureSequence $s) => $this->stopReason($lead, $s) === null && $this->enroll($lead, $s));
    }

    /** Inscreve perdidos há N dias nas sequências de reativação. */
    public function enrollLost(): int
    {
        $count = 0;
        NurtureSequence::query()->where('is_active', true)->where('trigger', NurtureSequence::TRIGGER_LOST)->get()
            ->each(function (NurtureSequence $sequence) use (&$count) {
                $until = now()->subDays($sequence->trigger_delay_days);
                Lead::query()->where('status', LeadStatus::LOST->value)
                    ->whereBetween('lost_at', [$until->copy()->subDays(self::LOST_LOOKBACK_DAYS), $until])
                    ->whereNotNull('email')->whereNull('email_opt_out_at')
                    ->whereDoesntHave('enrollments', fn ($q) => $q->where('sequence_id', $sequence->id))
                    ->each(function (Lead $lead) use ($sequence, &$count) {
                        $count += $this->enroll($lead, $sequence) ? 1 : 0;
                    });
            });

        return $count;
    }
}
