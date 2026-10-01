<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BrandingSettingController extends Controller
{
    /**
     * Tampilkan halaman Pengaturan Identitas & Logo Sistem
     */
    public function index()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $branding = [
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
        ];

        return Inertia::render('Admin/Settings/Branding', [
            'branding' => $branding,
        ]);
    }

    /**
     * Simpan perubahan identitas & upload logo/favicon
     */
    public function update(Request $request)
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        $validated = $request->validate([
            'app_name' => 'required|string|max:100',
            'app_tagline' => 'nullable|string|max:150',
            'app_short_name' => 'nullable|string|max:50',
            'app_icon_type' => 'required|in:pulse,leaf,capsule,factory,gauge',
            'app_logo_bg' => 'required|in:white,transparent,dark,gradient,custom',
            'app_logo_bg_color' => 'nullable|string|max:30',
            'app_logo_size' => 'required|in:sm,md,lg,xl',
            'app_logo_scale' => 'required|integer|min:40|max:100',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon' => 'nullable|file|mimes:ico,png,jpg,svg,webp|max:1024',
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
        ]);

        Setting::set('app_name', trim($validated['app_name']), 'branding');
        Setting::set('app_tagline', trim($validated['app_tagline'] ?? ''), 'branding');
        Setting::set('app_short_name', trim($validated['app_short_name'] ?? $validated['app_name']), 'branding');
        Setting::set('app_icon_type', $validated['app_icon_type'], 'branding');
        Setting::set('app_logo_bg', $validated['app_logo_bg'], 'branding');
        Setting::set('app_logo_bg_color', $validated['app_logo_bg_color'] ?? '#FFFFFF', 'branding');
        Setting::set('app_logo_size', $validated['app_logo_size'], 'branding');
        Setting::set('app_logo_scale', (int) $validated['app_logo_scale'], 'branding');

        // Handle Remove Logo
        if (! empty($validated['remove_logo'])) {
            $oldLogo = Setting::get('app_logo_url');
            if ($oldLogo && str_starts_with($oldLogo, '/storage/')) {
                $path = str_replace('/storage/', '', $oldLogo);
                Storage::disk('public')->delete($path);
            }
            Setting::set('app_logo_url', null, 'branding');
        }

        // Handle Remove Favicon
        if (! empty($validated['remove_favicon'])) {
            $oldFavicon = Setting::get('app_favicon_url');
            if ($oldFavicon && str_starts_with($oldFavicon, '/storage/')) {
                $path = str_replace('/storage/', '', $oldFavicon);
                Storage::disk('public')->delete($path);
            }
            Setting::set('app_favicon_url', null, 'branding');
        }

        // Upload Logo Baru
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $filename = 'logo_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('branding', $filename, 'public');
            Setting::set('app_logo_url', '/storage/'.$path, 'branding');
        }

        // Upload Favicon Baru
        if ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            $filename = 'favicon_'.time().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('branding', $filename, 'public');
            Setting::set('app_favicon_url', '/storage/'.$path, 'branding');
        }

        return redirect()->back()->with('success', 'Identitas sistem & logo berhasil diperbarui!');
    }

    /**
     * Reset ke identitas default (LinePulse)
     */
    public function reset()
    {
        if (auth()->user()->hasRole('manager')) {
            abort(403, 'Manager hanya memiliki akses baca.');
        }

        Setting::set('app_name', 'LinePulse', 'branding');
        Setting::set('app_tagline', 'Monitoring Produksi · Herbatech', 'branding');
        Setting::set('app_short_name', 'LinePulse', 'branding');
        Setting::set('app_icon_type', 'pulse', 'branding');
        Setting::set('app_logo_bg', 'white', 'branding');
        Setting::set('app_logo_bg_color', '#FFFFFF', 'branding');
        Setting::set('app_logo_size', 'md', 'branding');
        Setting::set('app_logo_scale', 80, 'branding');
        Setting::set('app_logo_url', null, 'branding');
        Setting::set('app_favicon_url', null, 'branding');

        return redirect()->back()->with('success', 'Identitas sistem berhasil direset ke default (LinePulse).');
    }
}
