<?php

namespace App\Livewire\Admin;

use App\Domain\Academies\Models\Academy;
use App\Domain\Notifications\Mail\SmtpTestMail;
use App\Domain\Platform\Concerns\RecordsAuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('System Settings')]
class SystemSettingsIndex extends Component
{
    use RecordsAuditLog;

    public string $activeTab = 'mail';

    // SMTP Mailer
    public string $mail_host = '';

    public int $mail_port = 587;

    public string $mail_encryption = 'tls'; // tls, ssl, none

    public ?string $mail_username = '';

    public ?string $mail_password = '';

    public bool $hasStoredPassword = false;

    public string $mail_from_address = '';

    public string $mail_from_name = '';

    // Testing
    public string $test_recipient = '';

    public ?string $testStatus = null;

    public ?string $testMessage = null;

    // General Settings
    public string $date_format = 'Y-m-d';

    public string $time_format = 'H:i';

    public int $session_lifetime = 120;

    // Notification Settings
    public bool $email_notifications_enabled = true;

    public bool $in_app_notifications_enabled = true;

    public function mount(): void
    {
        $academy = Academy::current() ?? Academy::first();
        $academyId = $academy?->getKey();

        $settings = Setting::query()
            ->where('group', 'mail')
            ->where(function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId)->orWhereNull('academy_id');
                } else {
                    $q->whereNull('academy_id');
                }
            })
            ->pluck('value', 'key');

        $this->mail_host = (string) ($settings['host'] ?? config('mail.mailers.smtp.host', '127.0.0.1'));
        $this->mail_port = (int) ($settings['port'] ?? config('mail.mailers.smtp.port', 587));
        $this->mail_encryption = (string) ($settings['encryption'] ?? 'tls');
        $this->mail_username = (string) ($settings['username'] ?? config('mail.mailers.smtp.username', ''));
        $this->hasStoredPassword = ! empty($settings['password']);
        $this->mail_from_address = (string) ($settings['from_address'] ?? config('mail.from.address', 'noreply@seo-academy.local'));
        $this->mail_from_name = (string) ($settings['from_name'] ?? config('mail.from.name', 'SEO Academy'));

        $this->test_recipient = Auth::user()?->email ?? '';

        $generalSettings = Setting::query()
            ->where('group', 'general')
            ->where(function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId)->orWhereNull('academy_id');
                } else {
                    $q->whereNull('academy_id');
                }
            })
            ->pluck('value', 'key');

        $this->date_format = (string) ($generalSettings['date_format'] ?? 'Y-m-d');
        $this->time_format = (string) ($generalSettings['time_format'] ?? 'H:i');
        $this->session_lifetime = (int) ($generalSettings['session_lifetime'] ?? config('session.lifetime', 120));

        $notifSettings = Setting::query()
            ->where('group', 'notifications')
            ->where(function ($q) use ($academyId) {
                if ($academyId) {
                    $q->where('academy_id', $academyId)->orWhereNull('academy_id');
                } else {
                    $q->whereNull('academy_id');
                }
            })
            ->pluck('value', 'key');

        $this->email_notifications_enabled = filter_var($notifSettings['email_notifications_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->in_app_notifications_enabled = filter_var($notifSettings['in_app_notifications_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['mail', 'general', 'notifications', 'storage'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function saveGeneralSettings(): void
    {
        $this->validate([
            'date_format' => 'required|string|in:Y-m-d,m/d/Y,d/m/Y,d.m.Y',
            'time_format' => 'required|string|in:H:i,g:i A',
            'session_lifetime' => 'required|integer|min:15|max:10080',
        ]);

        $academyId = Academy::current()?->getKey() ?? Academy::first()?->getKey();

        foreach ([
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'session_lifetime' => (string) $this->session_lifetime,
        ] as $key => $val) {
            Setting::updateOrCreate(
                ['academy_id' => $academyId, 'group' => 'general', 'key' => $key],
                ['value' => $val, 'type' => 'string', 'is_public' => true]
            );
        }

        $this->dispatch('toast', type: 'success', message: 'General application settings saved.');
        session()->flash('success', 'General application settings saved.');
    }

    public function saveNotificationSettings(): void
    {
        $academyId = Academy::current()?->getKey() ?? Academy::first()?->getKey();

        Setting::updateOrCreate(
            ['academy_id' => $academyId, 'group' => 'notifications', 'key' => 'email_notifications_enabled'],
            ['value' => $this->email_notifications_enabled ? '1' : '0', 'type' => 'boolean', 'is_public' => false]
        );

        Setting::updateOrCreate(
            ['academy_id' => $academyId, 'group' => 'notifications', 'key' => 'in_app_notifications_enabled'],
            ['value' => $this->in_app_notifications_enabled ? '1' : '0', 'type' => 'boolean', 'is_public' => false]
        );

        $this->dispatch('toast', type: 'success', message: 'Notification delivery settings saved.');
        session()->flash('success', 'Notification delivery settings saved.');
    }

    public function saveSettings(): void
    {
        $this->validate([
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|integer|min:1|max:65535',
            'mail_encryption' => 'required|in:tls,ssl,none',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        $academy = Academy::current() ?? Academy::first();
        $academyId = $academy?->getKey();

        $saveList = [
            'host' => $this->mail_host,
            'port' => (string) $this->mail_port,
            'encryption' => $this->mail_encryption,
            'username' => $this->mail_username,
            'from_address' => $this->mail_from_address,
            'from_name' => $this->mail_from_name,
        ];

        // Only update password if a new one was provided
        if (trim($this->mail_password) !== '') {
            $saveList['password'] = Crypt::encryptString($this->mail_password);
            $this->hasStoredPassword = true;
            $this->mail_password = '';
        }

        foreach ($saveList as $key => $val) {
            Setting::updateOrCreate(
                [
                    'academy_id' => $academyId,
                    'group' => 'mail',
                    'key' => $key,
                ],
                [
                    'value' => $val,
                    'type' => $key === 'password' ? 'encrypted' : 'string',
                    'is_public' => false,
                ]
            );
        }

        if ($academy) {
            $this->recordAudit(
                $academy,
                Auth::user(),
                'settings.mail_updated',
                Setting::class,
                'mail_config',
                [
                    'host' => $this->mail_host,
                    'port' => $this->mail_port,
                    'encryption' => $this->mail_encryption,
                    'username' => $this->mail_username,
                    'from_address' => $this->mail_from_address,
                ]
            );
        }

        $this->dispatch('toast', type: 'success', message: 'SMTP configuration saved securely.');
        session()->flash('success', 'SMTP configuration saved securely.');
    }

    public function testConnection(): void
    {
        $this->validate([
            'test_recipient' => 'required|email',
        ]);

        $this->testStatus = null;
        $this->testMessage = null;

        try {
            // Resolve password
            $resolvedPassword = '';
            if (trim($this->mail_password) !== '') {
                $resolvedPassword = $this->mail_password;
            } elseif ($this->hasStoredPassword) {
                $academyId = Academy::current()?->getKey();
                $enc = Setting::query()
                    ->where('group', 'mail')
                    ->where('key', 'password')
                    ->where(function ($q) use ($academyId) {
                        if ($academyId) {
                            $q->where('academy_id', $academyId)->orWhereNull('academy_id');
                        } else {
                            $q->whereNull('academy_id');
                        }
                    })
                    ->value('value');

                if ($enc) {
                    $resolvedPassword = Crypt::decryptString($enc);
                }
            }

            // Dynamically configure mailer for the test
            $scheme = match ($this->mail_encryption) {
                'ssl' => 'smtps',
                default => 'smtp',
            };

            config([
                'mail.mailers.test_smtp' => [
                    'transport' => 'smtp',
                    'host' => $this->mail_host,
                    'port' => $this->mail_port,
                    'encryption' => $this->mail_encryption === 'none' ? null : $this->mail_encryption,
                    'username' => $this->mail_username,
                    'password' => $resolvedPassword,
                    'timeout' => 10,
                ],
                'mail.from.address' => $this->mail_from_address,
                'mail.from.name' => $this->mail_from_name,
            ]);

            Mail::mailer('test_smtp')->to($this->test_recipient)->send(
                new SmtpTestMail(
                    senderName: Auth::user()?->name ?? 'Administrator',
                    testTimestamp: now()->toDateTimeString(),
                )
            );

            $this->testStatus = 'success';
            $this->testMessage = "Test message delivered successfully to [{$this->test_recipient}].";
        } catch (Throwable $e) {
            $this->testStatus = 'error';
            // Safe sanitized error message
            $msg = $e->getMessage();
            $sanitized = preg_replace('/password=[^\s&]+/i', 'password=***', $msg);
            $this->testMessage = "Connection test failed: {$sanitized}";
        }
    }

    public function render(): View
    {
        return view('livewire.admin.system-settings-index');
    }
}
