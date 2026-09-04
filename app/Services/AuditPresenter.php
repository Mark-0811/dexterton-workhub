<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditPresenter
{
    public function present(AuditEvent $event): array
    {
        $actor = $event->actor?->name ?? 'System';
        $subject = $this->subjectName($event->subject_type, $event->subject);
        $message = match ($event->event) {
            'auth.login' => "$actor signed in.",
            'auth.logout' => "$actor signed out.",
            'profile.updated' => "$actor updated their profile settings.",
            'request.created' => "$actor created request $subject.",
            'request.submit' => "$actor submitted request $subject.",
            'request.approve' => "$actor approved request $subject.",
            'request.reject' => "$actor rejected request $subject.",
            'request.cancel' => "$actor cancelled request $subject.",
            'workflow.run' => "$actor ran workflow $subject.",
            'admin.user.created' => "$actor added user $subject.",
            'admin.user.updated' => "$actor updated user $subject.",
            'admin.mail_settings.updated' => "$actor updated mail deadline alerts and task board colors.",
            'space.created' => "$actor created department space $subject.",
            'project.created' => "$actor created project $subject.",
            'project.updated' => "$actor updated project $subject.",
            'task.created' => "$actor assigned task $subject.",
            'task.updated' => "$actor updated task $subject.",
            'dataset.report_created' => "$actor created report $subject.",
            'odoo.csv_import' => "$actor imported Odoo CSV data.",
            'odoo.api_tested' => "$actor tested the Odoo API connection.",
            'odoo.api_sync' => "$actor synced data from Odoo.",
            default => "$actor performed ".Str::of($event->event)->replace(['.', '_'], ' ')->title().($subject ? " on $subject." : '.'),
        };

        return [
            'id' => $event->id,
            'message' => $message,
            'actor' => $actor,
            'event' => $event->event,
            'module' => Str::before($event->event, '.'),
            'subject' => $subject,
            'changed' => $this->changes($event->before ?? [], $event->after ?? []),
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'created_at' => $event->created_at?->format('M d, Y h:i A'),
            'technical' => json_encode([
                'before' => $event->before,
                'after' => $event->after,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
            ], JSON_PRETTY_PRINT),
        ];
    }

    private function subjectName(?string $type, ?Model $subject): string
    {
        if (! $subject) {
            return '';
        }

        return $subject->reference ?? $subject->code ?? $subject->name ?? $subject->title ?? class_basename((string) $type);
    }

    private function changes(array $before, array $after): array
    {
        return collect($after)->map(function ($value, $key) use ($before) {
            return ['field' => Str::of($key)->replace('_', ' ')->title(), 'from' => $before[$key] ?? null, 'to' => $value];
        })->values()->all();
    }
}
