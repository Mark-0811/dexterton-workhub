<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\AuditEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/MailSettings', [
            'mailSettings' => AppSetting::getValue('mail.deadline_alerts', self::defaultMailSettings()),
            'boardColors' => AppSetting::getValue('task.board_colors', self::defaultBoardColors()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'alert_days_before' => ['required', 'integer', 'min:1', 'max:60'],
            'send_time' => ['required', 'date_format:H:i'],
            'recipients' => ['required', 'array'],
            'recipients.owner' => ['required', 'boolean'],
            'recipients.assignee' => ['required', 'boolean'],
            'recipients.manager' => ['required', 'boolean'],
            'task_colors' => ['required', 'array'],
            'task_colors.todo' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'task_colors.doing' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'task_colors.review' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'task_colors.done' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'task_colors.cancelled' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $before = [
            'mail' => AppSetting::getValue('mail.deadline_alerts', self::defaultMailSettings()),
            'colors' => AppSetting::getValue('task.board_colors', self::defaultBoardColors()),
        ];

        AppSetting::setValue('mail.deadline_alerts', collect($data)->except('task_colors')->all(), $request->user()->id);
        AppSetting::setValue('task.board_colors', $data['task_colors'], $request->user()->id);

        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'event' => 'admin.mail_settings.updated',
            'before' => $before,
            'after' => ['mail' => collect($data)->except('task_colors')->all(), 'colors' => $data['task_colors']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Mail alerts and board colors saved.');
    }

    public static function defaultMailSettings(): array
    {
        return [
            'enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_from_address' => 'workhub@dexterton.com',
            'smtp_from_name' => 'Dexterton WorkHub',
            'alert_days_before' => 7,
            'send_time' => '08:00',
            'recipients' => ['owner' => true, 'assignee' => true, 'manager' => false],
        ];
    }

    public static function defaultBoardColors(): array
    {
        return [
            'todo' => '#eaf1ff',
            'doing' => '#fff3cd',
            'review' => '#ede7ff',
            'done' => '#d1e7dd',
            'cancelled' => '#f8d7da',
        ];
    }
}
