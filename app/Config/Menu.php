<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Menu sidebar. `roles` membatasi peran yang melihat item/section (kosong = semua peran),
 * `match` adalah pola URI untuk menandai menu aktif, `soon` menandai modul yang belum tersedia.
 */
class Menu extends BaseConfig
{
    /**
     * @var list<array{
     *     title: string,
     *     roles?: list<string>,
     *     items: list<array{label: string, url: string, icon: string, match?: string|list<string>, roles?: list<string>, soon?: bool}>
     * }>
     */
    public array $sections = [
        [
            'title' => '',
            'items' => [
                ['label' => 'Dashboard', 'url' => '/', 'icon' => 'home', 'match' => '/'],
            ],
        ],
        [
            'title' => 'Penjualan',
            'items' => [
                ['label' => 'Lead', 'url' => 'leads', 'icon' => 'funnel', 'match' => 'leads*'],
                ['label' => 'Perusahaan', 'url' => 'companies', 'icon' => 'building-office-2', 'match' => 'companies*'],
                ['label' => 'Kontak', 'url' => 'contacts', 'icon' => 'users', 'match' => 'contacts*'],
                ['label' => 'Pipeline', 'url' => 'deals/board', 'icon' => 'squares-2x2', 'match' => 'deals/board'],
                ['label' => 'Deal', 'url' => 'deals', 'icon' => 'banknotes', 'match' => ['deals', 'deals/new', 'deals/[0-9]*']],
                ['label' => 'Tugas', 'url' => 'tasks', 'icon' => 'clipboard-document-check', 'match' => 'tasks*'],
                ['label' => 'Penawaran', 'url' => 'quotations', 'icon' => 'document-text', 'match' => 'quotations*', 'soon' => true],
                ['label' => 'Produk', 'url' => 'products', 'icon' => 'cube', 'match' => 'products*', 'soon' => true],
            ],
        ],
        [
            'title' => 'Analisis',
            'roles' => ['admin', 'manager'],
            'items' => [
                ['label' => 'Laporan', 'url' => 'reports/performance', 'icon' => 'chart-bar', 'match' => 'reports*'],
            ],
        ],
        [
            'title' => 'Administrasi',
            'roles' => ['admin'],
            'items' => [
                ['label' => 'Pengguna', 'url' => 'admin/users', 'icon' => 'user-group', 'match' => 'admin/users*'],
                ['label' => 'Master Data', 'url' => 'admin/master', 'icon' => 'circle-stack', 'match' => 'admin/master*'],
                ['label' => 'Pengaturan', 'url' => 'admin/settings', 'icon' => 'cog-6-tooth', 'match' => 'admin/settings*'],
                ['label' => 'Log Audit', 'url' => 'admin/audit-logs', 'icon' => 'shield-check', 'match' => 'admin/audit-logs*'],
            ],
        ],
    ];

    /**
     * Section dan item yang terlihat oleh peran tertentu.
     *
     * @return list<array{title: string, items: list<array<string, mixed>>}>
     */
    public function forRole(string $role): array
    {
        $result = [];

        foreach ($this->sections as $section) {
            if (! empty($section['roles']) && ! in_array($role, $section['roles'], true)) {
                continue;
            }

            $items = array_values(array_filter(
                $section['items'],
                static fn (array $item): bool => empty($item['roles']) || in_array($role, $item['roles'], true),
            ));

            if ($items !== []) {
                $result[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return $result;
    }
}
