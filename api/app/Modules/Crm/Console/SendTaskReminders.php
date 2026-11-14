<?php

namespace App\Modules\Crm\Console;

use App\Modules\Crm\Mail\TaskReminderMail;
use App\Modules\Crm\Models\CrmSettings;
use App\Modules\Crm\Models\LeadTask;
use App\Modules\Crm\Services\LeadNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Avisa o responsável quando a tarefa vence (uma vez por prazo). */
class SendTaskReminders extends Command
{
    protected $signature = 'crm:task-reminders';

    protected $description = 'Envia lembretes de tarefas de follow-up que venceram';

    public function handle(LeadNotifier $notifier): int
    {
        $sent = 0;
        LeadTask::query()->withoutTenancy()->with(['lead' => fn ($q) => $q->withoutTenancy(), 'assignee', 'creator'])
            ->whereNull('completed_at')->whereNull('reminded_at')
            ->where('due_at', '<=', now())->where('due_at', '>=', now()->subDay())
            ->chunkById(100, function ($tasks) use ($notifier, &$sent) {
                foreach ($tasks as $task) {
                    $task->forceFill(['reminded_at' => now()])->save();
                    if (! $task->lead) continue;

                    $url = $notifier->leadUrl($task->lead);
                    $to = $task->assignee?->email ?? $task->creator?->email;
                    if ($to) {
                        Mail::to($to)->send(new TaskReminderMail($task, $url));
                    }
                    $notifier->telegram(CrmSettings::current($task->tenant_id), sprintf(
                        "⏰ <b>%s</b>\nLead: %s\n%s",
                        e($task->title), e($task->lead->name ?: $task->lead->ref), $url,
                    ));
                    $sent++;
                }
            });

        $this->info("{$sent} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
