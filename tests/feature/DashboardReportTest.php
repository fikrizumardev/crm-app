<?php

use Tests\Support\AppTestCase;

/**
 * KPI dashboard dan laporan (T4.01–T4.04).
 *
 * @internal
 */
final class DashboardReportTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Andi: 2 deal terbuka, 1 Won bulan ini, 1 Lost. Siti: 1 terbuka. Putri (tim lain): 1 Won.
        $this->makeDeal('andi.saputra', 'Prospek', ['amount' => 100000000]);
        $this->makeDeal('andi.saputra', 'Negosiasi', ['amount' => 200000000, 'expected_close_date' => date('Y-m-d')]);
        $this->makeDeal('andi.saputra', 'Won', ['amount' => 300000000, 'closed_at' => date('Y-m-d H:i:s')]);
        $this->makeDeal('andi.saputra', 'Lost', ['amount' => 50000000, 'closed_at' => date('Y-m-d H:i:s')]);
        $this->makeDeal('siti.rahma', 'Proposal', ['amount' => 80000000]);
        $this->makeDeal('putri.ayu', 'Won', ['amount' => 500000000, 'closed_at' => date('Y-m-d H:i:s')]);

        $this->makeLead('andi.saputra', ['source_id' => $this->masterId('lead_sources', 'Pameran')]);
        $this->makeLead('andi.saputra', ['source_id' => $this->masterId('lead_sources', 'Pameran'), 'status' => 'converted']);
        $this->makeLead('siti.rahma');
    }

    public function testKpiSalesHanyaMiliknya(): void
    {
        $kpi = service('dashboard')->compute(service('dashboard')->ownerIds($this->user('andi.saputra'), null))['kpi'];

        $this->assertSame(2, $kpi['newLeads']);
        $this->assertSame(2, $kpi['openDeals']);
        $this->assertSame(300000000.0, $kpi['pipelineValue']);
        $this->assertSame(160000000.0, $kpi['weightedValue']); // 100jt×10% + 200jt×75%
        $this->assertSame(300000000.0, $kpi['wonValue']);
        $this->assertSame(50.0, $kpi['winRate']);
    }

    public function testKpiManagerTimDanFilterPerSales(): void
    {
        $dashboard = service('dashboard');
        $dewi      = $this->user('dewi.lestari');

        $team = $dashboard->compute($dashboard->ownerIds($dewi, null))['kpi'];
        $this->assertSame(3, $team['openDeals']);
        $this->assertSame(300000000.0, $team['wonValue']);

        $siti = $dashboard->compute($dashboard->ownerIds($dewi, $this->user('siti.rahma')->id))['kpi'];
        $this->assertSame(1, $siti['openDeals']);
        $this->assertNull($siti['winRate']);

        // Filter ke sales tim lain diabaikan (kembali ke cakupan tim).
        $this->assertSame($dashboard->ownerIds($dewi, null), $dashboard->ownerIds($dewi, $this->user('putri.ayu')->id));

        $all = $dashboard->compute($dashboard->ownerIds($this->user('admin'), null))['kpi'];
        $this->assertSame(800000000.0, $all['wonValue']);
    }

    public function testGrafikPipelineDanTren(): void
    {
        $data = service('dashboard')->compute(null);

        $this->assertSame(['Prospek', 'Kualifikasi', 'Proposal', 'Negosiasi'], $data['pipeline']['labels']);
        $this->assertSame([100000000.0, 0.0, 80000000.0, 200000000.0], $data['pipeline']['values']);
        $this->assertCount(6, $data['trend']['values']);
        $this->assertSame(800000000.0, end($data['trend']['values']));
    }

    public function testDashboardDiCacheLimaMenit(): void
    {
        $andi  = $this->user('andi.saputra');
        $first = service('dashboard')->data($andi);
        $this->makeDeal('andi.saputra', 'Prospek', ['amount' => 999000000]);

        $this->assertSame($first['kpi'], service('dashboard')->data($andi)['kpi']);

        cache()->clean();
        $this->assertSame(3, service('dashboard')->data($andi)['kpi']['openDeals']);
    }

    public function testHalamanDashboard(): void
    {
        $result = $this->actingAs('dewi.lestari')->get('/?owner=' . $this->user('andi.saputra')->id);

        $result->assertOK();
        $result->assertSee('chart-pipeline');
        $result->assertSee('Rp 300 jt');
        $result->assertSee('Andi Saputra');
    }

    public function testLaporanKinerjaSales(): void
    {
        $data = service('reports')->salesPerformance($this->user('dewi.lestari'), date('Y-m-01'), date('Y-m-d'));

        $this->assertSame(['Andi Saputra', 'Budi Santoso', 'Siti Rahmawati'], array_column($data['rows'], 'name'));
        $andi = $data['rows'][0];
        $this->assertSame(2, $andi['leads']);
        $this->assertSame(4, $andi['deals']);
        $this->assertSame(1, $andi['won_count']);
        $this->assertSame(300000000.0, $andi['won_value']);
        $this->assertSame(1, $andi['lost_count']);
        $this->assertSame(50.0, $andi['win_rate']);
        $this->assertSame(5, $data['totals']['deals']);
    }

    public function testLaporanWinLossSumberDanForecast(): void
    {
        $admin = $this->user('admin');

        $winLoss = service('reports')->winLoss($admin, date('Y-m-01'), date('Y-m-d'));
        $this->assertSame(2, $winLoss['won_count']);
        $this->assertSame(1, $winLoss['lost_count']);
        $this->assertSame('Harga', $winLoss['reasons'][0]['name']);
        $this->assertSame(100.0, $winLoss['reasons'][0]['percent']);

        $sources = service('reports')->leadSources($admin, date('Y-m-01'), date('Y-m-d'));
        $this->assertSame('Pameran', $sources[0]['name']);
        $this->assertSame(2, $sources[0]['leads']);
        $this->assertSame(50.0, $sources[0]['rate']);

        $forecast = service('reports')->forecast($admin);
        $this->assertSame(3, $forecast['totals']['count']);
        $this->assertSame(380000000.0, $forecast['totals']['amount']);
        $this->assertSame(200000000.0 * 0.75, $forecast['rows'][1]['weighted']); // bulan berjalan
    }

    public function testAksesLaporanPerPeran(): void
    {
        $this->actingAs('andi.saputra')->get('reports/performance')->assertStatus(403);
        $this->actingAs('dewi.lestari')->get('reports/performance')->assertOK();

        $result = $this->actingAs('hendra.wijaya')->get('reports/performance?preset=this_month');
        $result->assertSee('Putri Ayu Ningsih');
        $result->assertDontSee('Andi Saputra');
    }

    public function testEksporCsvLaporan(): void
    {
        $result = $this->actingAs('admin')->get('reports/performance?preset=this_month&export=csv');

        $result->assertOK();
        $csv = $result->response()->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBFSales,\"Lead baru\"", $csv);
        $this->assertStringContainsString('"Andi Saputra",2,4,1,300000000,1,50', $csv);
        $this->assertStringContainsString('Total,', $csv);

        $this->assertStringContainsString('Harga', $this->actingAs('admin')->get('reports/win-loss?export=csv')->response()->getBody());
        $this->assertStringContainsString('Forecast tertimbang', $this->actingAs('admin')->get('reports/forecast?export=csv')->response()->getBody());
    }

    public function testPeriodeLaporan(): void
    {
        $reports = service('reports');

        $this->assertSame(['from' => date('Y-m-01'), 'to' => date('Y-m-d')], array_intersect_key($reports->period(null, null, null), ['from' => 1, 'to' => 1]));
        $this->assertSame(date('Y-01-01'), $reports->period('this_year', null, null)['from']);

        $custom = $reports->period('', '2026-03-31', '2026-03-01');
        $this->assertSame(['2026-03-01', '2026-03-31', null], [$custom['from'], $custom['to'], $custom['preset']]);
    }
}
