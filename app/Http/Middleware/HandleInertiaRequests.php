<?php

namespace App\Http\Middleware;

use App\Models\LaporanHarian;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $gitDir = base_path('.git');
        if (is_dir($gitDir)) {
            $head = file_get_contents($gitDir.'/HEAD');
            if (str_starts_with($head, 'ref: ')) {
                $refFile = $gitDir.'/'.trim(substr($head, 5));
                if (file_exists($refFile)) {
                    return trim(file_get_contents($refFile));
                }
            } else {
                return trim($head);
            }
        }

        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? array_merge($request->user()->toArray(), [
                    'roles' => $request->user()->getRoleNames(),
                    'role' => $request->user()->getRoleNames()->first(),
                ]) : null,
            ],
            'active_production' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                $query = LaporanHarian::with(['produk:id,nama_produk', 'line:id,nama_line'])
                    ->whereIn('timer_status', ['start', 'pause'])
                    ->where('status', '!=', 'locked');

                if ($user->hasRole('leader')) {
                    $query->where('user_id', $user->id);
                }

                $active = $query->latest('updated_at')->first();
                if (! $active) {
                    return null;
                }

                return [
                    'id' => $active->id,
                    'batch_number' => $active->batch_number,
                    'proses' => $active->proses,
                    'tanggal' => $active->tanggal,
                    'timer_status' => $active->timer_status,
                    'start_time' => $active->start_time,
                    'start_time_at' => $active->start_time_at?->toIso8601String(),
                    'total_pause_menit' => $active->total_pause_menit ?? 0,
                    'pause_started_at' => $active->pause_started_at,
                    'produk' => $active->produk ? [
                        'id' => $active->produk->id,
                        'nama_produk' => $active->produk->nama_produk,
                    ] : null,
                    'line' => $active->line ? [
                        'id' => $active->line->id,
                        'nama_line' => $active->line->nama_line,
                    ] : null,
                ];
            },
            'branding' => [
                'app_name' => Setting::get('app_name', 'LinePulse'),
                'app_tagline' => Setting::get('app_tagline', 'Monitoring Produksi · Herbatech'),
                'app_short_name' => Setting::get('app_short_name', 'LinePulse'),
                'app_logo_url' => Setting::get('app_logo_url'),
                'app_favicon_url' => Setting::get('app_favicon_url'),
                'app_icon_type' => Setting::get('app_icon_type', 'pulse'),
                'app_logo_bg' => Setting::get('app_logo_bg', 'white'),
                'app_logo_bg_color' => Setting::get('app_logo_bg_color', '#FFFFFF'),
                'app_logo_size' => Setting::get('app_logo_size', 'md'),
                'app_logo_scale' => (int) Setting::get('app_logo_scale', 80),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
