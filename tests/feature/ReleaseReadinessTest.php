<?php

use App\Database\Seeds\ProductionSeeder;
use App\Models\DealModel;
use CodeIgniter\Events\Events;
use CodeIgniter\Router\RouteCollection;
use Tests\Support\AppTestCase;

/**
 * Kesiapan rilis: keamanan rute, CSP, N+1, Kanban data besar, seeder produksi (T5.05–T5.09).
 *
 * @internal
 */
final class ReleaseReadinessTest extends AppTestCase
{
    public function testSemuaRuteKecualiLoginWajibAuthDanRuteTerbatasPakaiRole(): void
    {
        /** @var RouteCollection $routes */
        $routes = service('routes');
        $routes->loadRoutes();

        $checked = 0;

        foreach (['GET', 'POST'] as $verb) {
            foreach (array_keys($routes->getRoutes($verb)) as $route) {
                $filters = $routes->getFiltersForRoute($route, $verb);

                if ($route === 'login') {
                    $this->assertNotContains('auth', $filters);

                    continue;
                }

                $this->assertContains('auth', $filters, "Rute {$verb} {$route} tidak dilindungi auth");
                $this->assertContains('forcepassword', $filters, "Rute {$verb} {$route} tanpa forcepassword");

                if (str_starts_with($route, 'admin/') || preg_match('#^products/(new|\(|$)#', $route) === 1 && $verb === 'POST') {
                    $this->assertContains('role:admin', $filters, "Rute {$verb} {$route} harus khusus admin");
                }

                if (str_starts_with($route, 'reports') || str_ends_with($route, '/export') || str_ends_with($route, '/reassign')) {
                    $this->assertContains('role:admin,manager', $filters, "Rute {$verb} {$route} harus admin/manager");
                }

                $checked++;
            }
        }

        $this->assertGreaterThan(80, $checked);
    }

    public function testIdorSemuaEntitasSebagaiSales(): void
    {
        $lead    = $this->makeLead('siti.rahma');
        $company = $this->makeCompany('siti.rahma');
        $contact = $this->makeContact('siti.rahma');
        $deal    = $this->makeDeal('siti.rahma');
        $task    = $this->makeTask('siti.rahma');

        $gets = [
            "leads/{$lead}", "leads/{$lead}/edit", "leads/{$lead}/convert",
            "companies/{$company}", "companies/{$company}/edit",
            "contacts/{$contact}", "contacts/{$contact}/edit",
            "deals/{$deal}", "deals/{$deal}/edit", 'quotations/new?deal=' . $deal, 'deals/new?company=' . $company,
        ];

        foreach ($gets as $path) {
            $status = $this->actingAs('andi.saputra')->get($path)->response()->getStatusCode();

            // deals/new?company=… tetap terbuka tetapi perusahaan orang lain tidak diprefill.
            if ($path === 'deals/new?company=' . $company) {
                $this->assertSame(200, $status);
                $this->actingAs('andi.saputra')->get($path)->assertDontSee('PT Uji siti.rahma');

                continue;
            }

            $this->assertSame(404, $status, "GET {$path} seharusnya 404");
        }

        $posts = [
            "leads/{$lead}", "leads/{$lead}/delete", "leads/{$lead}/convert",
            "companies/{$company}", "companies/{$company}/delete",
            "contacts/{$contact}", "contacts/{$contact}/delete",
            "deals/{$deal}", "deals/{$deal}/delete", "deals/{$deal}/stage",
            "tasks/{$task}/done", "tasks/{$task}/delete",
        ];

        foreach ($posts as $path) {
            $this->assertSame(404, $this->actingAs('andi.saputra')->post($path, ['name' => 'Diretas'])->response()->getStatusCode(), "POST {$path} seharusnya 404");
        }

        $this->seeInDatabase('leads', ['id' => $lead, 'name' => 'Lead siti.rahma', 'deleted_at' => null]);
        $this->seeInDatabase('tasks', ['id' => $task, 'status' => 'open']);
    }

    public function testHeaderKeamananDanCsp(): void
    {
        $response = $this->get('login')->response();
        // Header CSP ditambahkan CI saat response dikirim; di test dipicu manual.
        $response->getCSP()->finalize($response);
        $csp = $response->getHeaderLine('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net 'unsafe-eval' 'nonce-", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
        // Nonce style membuat browser mengabaikan unsafe-inline (dibutuhkan Tailwind CDN). Kint/Debug Toolbar
        // menambahkannya hanya saat CI_DEBUG aktif; di production (CI_DEBUG mati) tidak boleh ada.
        if (! CI_DEBUG) {
            $this->assertStringNotContainsString("style-src 'self' 'unsafe-inline' 'nonce-", $csp);
        }
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

        // Semua skrip inline di halaman memakai nonce dari header.
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $body = $response->getBody();
        preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*application\/json)[^>]*>/', $body, $inline);
        $this->assertNotEmpty($inline[0]);

        foreach ($inline[0] as $tag) {
            $this->assertStringContainsString('nonce="' . $m[1] . '"', $tag);
        }

        // Skrip CDN dikunci versinya.
        preg_match_all('/<script[^>]+src="(https:\/\/cdn[^"]+)"/', $body, $cdn);

        foreach ($cdn[1] as $src) {
            $this->assertMatchesRegularExpression('/@\d+\.\d+\.\d+|\/\d+\.\d+\.\d+/', $src, "Versi CDN belum dikunci: {$src}");
        }
    }

    public function testDaftarTidakMemicuQueryPerBaris(): void
    {
        $count = function (string $path): int {
            $queries = 0;
            $counter = static function () use (&$queries): void {
                $queries++;
            };
            Events::on('DBQuery', $counter);
            $this->actingAs('dewi.lestari')->get($path)->assertOK();
            Events::removeListener('DBQuery', $counter);

            return $queries;
        };

        foreach (['leads' => fn () => $this->makeLead('andi.saputra'), 'deals' => fn () => $this->makeDeal('siti.rahma'), 'tasks' => fn () => $this->makeTask('budi.santoso'), 'companies' => fn () => $this->makeCompany('andi.saputra')] as $path => $make) {
            for ($i = 0; $i < 3; $i++) {
                $make();
            }

            $count($path); // pemanasan cache (pengaturan, sesi, dsb.)
            $few = $count($path);

            for ($i = 0; $i < 20; $i++) {
                $make();
            }

            $many = $count($path);
            $this->assertSame($few, $many, "Halaman {$path}: jumlah query naik seiring jumlah baris ({$few} → {$many})");
        }
    }

    public function testKanbanDibatasiDanTotalTetapAkurat(): void
    {
        $stage = $this->masterId('pipeline_stages', 'Prospek');
        $rows  = [];

        for ($i = 0; $i < 60; $i++) {
            $rows[] = [
                'title' => 'Deal massal ' . $i, 'stage_id' => $stage, 'amount' => 1000000, 'probability' => 10,
                'owner_id' => $this->user('andi.saputra')->id, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $this->db->table('deals')->insertBatch($rows);
        $andi = $this->user('andi.saputra');

        $this->assertCount(50, model(DealModel::class)->forBoardStage($andi, null, $stage, 50));
        $this->assertSame(['count' => 60, 'sum' => 60000000.0], model(DealModel::class)->boardTotals($andi, null)[$stage]);

        $result = $this->actingAs('andi.saputra')->get('deals/board');
        $result->assertSee('+10 deal lainnya');
        $result->assertSee('Rp 60.000.000');
    }

    public function testProductionSeederMembuatAdminSekali(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->table('users')->emptyTable();
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        ob_start();
        $this->seed(ProductionSeeder::class);
        $this->seed(ProductionSeeder::class);
        ob_end_clean();

        $admins = $this->db->table('users')->get()->getResultArray();
        $this->assertCount(1, $admins);
        $this->assertSame('admin', $admins[0]['username']);
        $this->assertSame('1', (string) $admins[0]['must_change_password']);
        $this->assertFalse(password_verify('Admin#2026', $admins[0]['password_hash']));
        $this->assertSame(0, $this->db->table('leads')->countAllResults());
        $this->assertSame(6, $this->db->table('pipeline_stages')->countAllResults());
    }
}
