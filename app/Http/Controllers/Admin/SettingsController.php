<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\AiRequest;
use App\Models\QuickReplyTemplate;
use App\Services\Ai\AiManager;
use App\Services\AuditService;
use App\Services\SettingService;
use App\Support\Branding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * System Settings (IT Admin). Nilai disimpan di tabel `settings`; rahasia (API key, DB) tetap di .env.
 */
class SettingsController extends Controller
{
    /** form field => [setting key, type] */
    private const MAP = [
        'app_name' => ['branding.app_name', 'string'],
        'short_name' => ['branding.short_name', 'string'],
        'organization_name' => ['organization.name', 'string'],
        'support_team' => ['organization.support_team', 'string'],
        'contact_email' => ['contact.email', 'string'],
        'contact_phone' => ['contact.phone', 'string'],
        'contact_address' => ['contact.address', 'string'],
        'primary_color' => ['branding.primary_color', 'string'],
        'ticket_number_prefix' => ['ticket.number_prefix', 'string'],
        'auto_close_days' => ['ticket.auto_close_days', 'int'],
        'upload_max_kb' => ['upload.max_kb', 'int'],
        'upload_max_files' => ['upload.max_files', 'int'],
        'kb_public_enabled' => ['kb.public_enabled', 'bool'],
        'ai_enabled' => ['ai.enabled', 'bool'],
    ];

    public function __construct(private readonly SettingService $settings, private readonly AuditService $audit) {}

    public function index(AiManager $ai): View
    {
        $this->authorize('access-admin');

        return view('admin.settings.index', [
            'values' => [
                'app_name' => Branding::appName(),
                'short_name' => Branding::shortName(),
                'organization_name' => Branding::organization(),
                'support_team' => Branding::supportTeam(),
                'contact_email' => Branding::contact()['email'],
                'contact_phone' => Branding::contact()['phone'],
                'contact_address' => Branding::contact()['address'],
                'primary_color' => Branding::primaryColor(),
                'ticket_number_prefix' => $this->settings->get('ticket.number_prefix', 'IT'),
                'auto_close_days' => $this->settings->get('ticket.auto_close_days', 3),
                'upload_max_kb' => $this->settings->get('upload.max_kb', 5120),
                'upload_max_files' => $this->settings->get('upload.max_files', 5),
                'kb_public_enabled' => (bool) $this->settings->get('kb.public_enabled', true),
                'ai_enabled' => (bool) $this->settings->get('ai.enabled', false),
            ],
            'logoUrl' => Branding::logoUrl(),
            'ai' => [
                'provider' => $ai->providerName(),
                'model' => $ai->model(),
                'key_configured' => filled(config('ai.api_key')),
                'reason' => $ai->unavailableReason(),
                'today' => AiRequest::where('created_at', '>=', now()->startOfDay())->selectRaw("COUNT(*) total, SUM(status = 'success') ok, SUM(status <> 'success') failed, COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(output_tokens),0) output_tokens")->first(),
                'month' => AiRequest::where('created_at', '>=', now()->subDays(30))->selectRaw('COUNT(*) total, COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(output_tokens),0) output_tokens')->first(),
                'limits' => ['per_user_hour' => config('ai.rate_per_user_per_hour'), 'daily' => config('ai.daily_limit')],
            ],
            'templates' => QuickReplyTemplate::orderBy('sort_order')->orderBy('id')->get(),
            'system' => [
                'edition' => config('helpdesk.edition'),
                'timezone' => config('app.timezone'),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'cron_hint' => '* * * * * php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
            ],
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $before = collect(self::MAP)->mapWithKeys(fn ($m, $field) => [$m[0] => $this->settings->get($m[0])])->all();

        foreach (self::MAP as $field => [$key, $type]) {
            $value = $data[$field] ?? null;
            $this->settings->set($key, $type === 'bool' ? ($value ? '1' : '0') : ($value === null ? '' : (string) $value), $type);
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
        }
        if ($request->hasFile('logo')) {
            $this->deleteLogo();
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$request->file('logo')->getMimeType()] ?? 'png';
            $path = $request->file('logo')->storeAs('branding', 'logo-'.Str::lower(Str::random(8)).'.'.$ext, 'local');
            $this->settings->set('branding.logo_path', $path, 'string');
        }

        $after = collect(self::MAP)->mapWithKeys(fn ($m) => [$m[0] => $this->settings->get($m[0])])->all();
        $changed = array_keys(array_filter($after, fn ($v, $k) => (string) ($before[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH));
        $this->audit->log('settings.updated', description: 'Mengubah System Settings',
            oldValues: array_intersect_key($before, array_flip($changed)), newValues: array_intersect_key($after, array_flip($changed)) + ($request->hasFile('logo') ? ['logo' => 'diganti'] : []));

        return back()->with('success', 'Pengaturan disimpan.');
    }

    /**
     * Untuk hosting tanpa Cron: IT Admin menjalankan tugas terjadwal secara manual (tidak pernah dipicu request user biasa).
     */
    public function runScheduledTasks(): RedirectResponse
    {
        $this->authorize('access-admin');
        Artisan::call('tickets:auto-close');
        $output = trim(Artisan::output());
        Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 30, '--tries' => 3]);
        $this->audit->log('settings.scheduled_tasks_run', description: 'Menjalankan tugas terjadwal manual: '.$output);

        return back()->with('success', 'Tugas terjadwal dijalankan. '.$output);
    }

    private function deleteLogo(): void
    {
        if ($old = $this->settings->get('branding.logo_path')) {
            Storage::disk('local')->delete($old);
        }
        $this->settings->set('branding.logo_path', '', 'string');
    }
}
