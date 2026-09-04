<?php

namespace App\Console\Commands;

use App\Http\Controllers\MailSettingsController;
use App\Models\AppSetting;
use App\Models\OutboxEvent;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendProjectDeadlineAlerts extends Command
{
    protected $signature = 'workhub:deadline-alerts {--dry-run : Report due records without creating outbox messages}';

    protected $description = 'Create email outbox alerts for projects and tasks nearing their deadlines.';

    public function handle(): int
    {
        $settings = AppSetting::getValue('mail.deadline_alerts', MailSettingsController::defaultMailSettings());

        if (! ($settings['enabled'] ?? false)) {
            $this->info('Deadline mail alerts are disabled.');
            return self::SUCCESS;
        }

        $daysBefore = (int) ($settings['alert_days_before'] ?? 7);
        $sendTime = (string) ($settings['send_time'] ?? '08:00');
        $today = CarbonImmutable::today();
        $until = $today->addDays($daysBefore);
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;

        if (! $dryRun && now()->format('H:i') !== $sendTime) {
            $this->info("Deadline mail alerts are scheduled for {$sendTime}.");
            return self::SUCCESS;
        }

        $projects = Project::with(['owner'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('due_at', [$today->toDateString(), $until->toDateString()])
            ->get();

        foreach ($projects as $project) {
            $created += $this->queueAlert('project.deadline.near', $project->id, [
                'subject' => "Project {$project->code} is near deadline",
                'message' => "{$project->name} is due on {$project->due_at?->toDateString()}.",
                'project_id' => $project->id,
                'project_code' => $project->code,
                'project_name' => $project->name,
                'due_at' => $project->due_at?->toDateString(),
                'recipients' => array_filter([$project->owner?->email]),
                'settings' => $settings,
            ], $dryRun);
        }

        $tasks = Task::with(['project.owner', 'assignee.manager'])
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereBetween('due_at', [$today->startOfDay(), $until->endOfDay()])
            ->get();

        foreach ($tasks as $task) {
            $recipients = [];
            if (($settings['recipients']['assignee'] ?? true) && $task->assignee?->email) {
                $recipients[] = $task->assignee->email;
            }
            if (($settings['recipients']['owner'] ?? true) && $task->project?->owner?->email) {
                $recipients[] = $task->project->owner->email;
            }
            if (($settings['recipients']['manager'] ?? false) && $task->assignee?->manager?->email) {
                $recipients[] = $task->assignee->manager->email;
            }

            $created += $this->queueAlert('task.deadline.near', $task->id, [
                'subject' => "Task due soon: {$task->title}",
                'message' => "{$task->title} is due on {$task->due_at?->toDateString()} for {$task->project?->name}.",
                'project_id' => $task->project_id,
                'project_code' => $task->project?->code,
                'task_id' => $task->id,
                'task_title' => $task->title,
                'due_at' => $task->due_at?->toDateString(),
                'recipients' => array_values(array_unique($recipients)),
                'settings' => $settings,
            ], $dryRun);
        }

        $this->info(($dryRun ? 'Found' : 'Queued')." {$created} deadline alert(s).");

        return self::SUCCESS;
    }

    private function queueAlert(string $topic, string $aggregateId, array $payload, bool $dryRun): int
    {
        if ($dryRun) {
            $this->line($payload['subject'].' → '.implode(', ', $payload['recipients']));
            return 1;
        }

        $alreadyQueued = OutboxEvent::where('topic', $topic)
            ->where('aggregate_id', $aggregateId)
            ->whereDate('created_at', today())
            ->exists();

        if ($alreadyQueued || empty($payload['recipients'])) {
            return 0;
        }

        OutboxEvent::create([
            'topic' => $topic,
            'aggregate_type' => str_starts_with($topic, 'project.') ? Project::class : Task::class,
            'aggregate_id' => $aggregateId,
            'payload' => $payload,
            'available_at' => now(),
        ]);

        return 1;
    }
}
