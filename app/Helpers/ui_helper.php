<?php

/**
 * Helper kecil untuk view: ikon, pengaturan aplikasi, URL urut tabel.
 */

use App\Models\SettingModel;

if (! function_exists('component')) {
    /**
     * Render partial `app/Views/components/{name}.php` dengan data yang terisolasi.
     *
     * Renderer bersama CI4 menyimpan data antar pemanggilan view(), sehingga variabel
     * opsional komponen (mis. `$error`, `$color`) bisa terbawa ke pemanggilan berikutnya.
     * Instance renderer baru mencegah hal itu.
     *
     * @param array<string, mixed> $data
     */
    function component(string $name, array $data = []): string
    {
        return single_service('renderer')->setData($data, 'raw')->render('components/' . $name, null, false);
    }
}

if (! function_exists('icon')) {
    /**
     * Render ikon Heroicons (SVG inline) dari `app/Views/components/icons/{name}.php`.
     */
    function icon(string $name, string $class = 'h-5 w-5'): string
    {
        return component('icons/' . $name, ['class' => $class]);
    }
}

if (! function_exists('app_setting')) {
    /**
     * Nilai pengaturan aplikasi dari tabel `settings`.
     */
    function app_setting(string $key, ?string $default = null): ?string
    {
        try {
            return model(SettingModel::class)->getValue($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }
}

if (! function_exists('app_logo_url')) {
    /**
     * URL logo perusahaan dari pengaturan; null bila belum diunggah.
     */
    function app_logo_url(): ?string
    {
        $file = basename((string) app_setting('company_logo', ''));

        return $file !== '' && is_file(FCPATH . 'uploads/' . $file) ? base_url('uploads/' . $file) : null;
    }
}

if (! function_exists('sort_url')) {
    /**
     * URL halaman saat ini dengan parameter sort/dir baru (toggle arah bila kolom sama).
     */
    function sort_url(string $column): string
    {
        $request = service('request');
        $query   = $request->getGet();
        $current = $query['sort'] ?? null;
        $dir     = ($current === $column && ($query['dir'] ?? 'asc') === 'asc') ? 'desc' : 'asc';

        unset($query['page']);
        $query['sort'] = $column;
        $query['dir']  = $dir;

        return current_url() . '?' . http_build_query($query);
    }
}

if (! function_exists('query_url')) {
    /**
     * URL dengan query string saat ini ditambah/ditimpa parameter tertentu.
     *
     * @param array<string, mixed> $params
     */
    function query_url(string $path, array $params = []): string
    {
        $query = array_merge(service('request')->getGet(), $params);
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

        return site_url($path) . ($query === [] ? '' : '?' . http_build_query($query));
    }
}

if (! function_exists('form_errors')) {
    /**
     * Pesan error validasi dari flash session (hasil redirect withInput).
     *
     * @return array<string, string>
     */
    function form_errors(): array
    {
        $errors = session('errors');

        return is_array($errors) ? $errors : [];
    }
}
