<?php

use App\Database\Seeds\ActivitySeeder;
use App\Database\Seeds\CompanySeeder;
use App\Database\Seeds\ContactSeeder;
use App\Database\Seeds\DealSeeder;
use App\Database\Seeds\DemoData;
use App\Database\Seeds\LeadSeeder;
use App\Database\Seeds\ProductSeeder;
use App\Database\Seeds\TaskSeeder;
use Tests\Support\AppTestCase;

/**
 * Seeder data demo Fase 2 (T2.10, T2.11).
 *
 * @internal
 */
final class DemoSeederTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
        $this->seed(CompanySeeder::class);
        $this->seed(ContactSeeder::class);
        $this->seed(LeadSeeder::class);
    }

    public function testJumlahDanShowcase(): void
    {
        $this->assertSame(25, $this->db->table('products')->countAllResults());
        $this->assertSame(60, $this->db->table('companies')->countAllResults());
        $this->assertSame(120, $this->db->table('leads')->countAllResults());

        $contacts = $this->db->table('contacts')->countAllResults();
        $this->assertGreaterThanOrEqual(120, $contacts);
        $this->assertLessThanOrEqual(180, $contacts);

        $company = $this->db->table('companies')->where('name', 'PT Sinar Logistik Nusantara')->get()->getRowArray();
        $this->assertSame('Surabaya', $company['city']);
        $this->assertSame('Jawa Timur', $company['province']);
        $this->assertSame('250', (string) $company['employee_count']);
        $this->assertSame($this->user('andi.saputra')->id, (int) $company['owner_id']);

        $this->seeInDatabase('contacts', [
            'company_id' => $company['id'],
            'first_name' => 'Bambang',
            'last_name'  => 'Hartono',
            'job_title'  => 'Kepala IT',
            'phone'      => '081234567810',
            'email'      => 'bambang.hartono@sinarlogistik.co.id',
        ]);
    }

    public function testDistribusiStatusLeadDanKonsistensiKonversi(): void
    {
        $rows   = $this->db->table('leads')->select('status, COUNT(*) AS total')->groupBy('status')->get()->getResultArray();
        $counts = array_map('intval', array_column($rows, 'total', 'status'));
        ksort($counts);
        $expected = LeadSeeder::DISTRIBUTION;
        ksort($expected);
        $this->assertSame($expected, $counts);

        $broken = $this->db->table('leads l')
            ->join('contacts c', 'c.id = l.converted_contact_id', 'left')
            ->where('l.status', 'converted')
            ->groupStart()->where('c.id', null)->orWhere('c.company_id != l.converted_company_id')->groupEnd()
            ->countAllResults();
        $this->assertSame(0, $broken);
    }

    public function testKotaDanTanggalRelatif(): void
    {
        $cities = $this->db->table('companies')->select('city')->distinct()->get()->getResultArray();
        $this->assertCount(8, $cities);

        $future = $this->db->table('leads')->where('created_at >', date('Y-m-d H:i:s'))->countAllResults();
        $this->assertSame(0, $future);

        $oldest = $this->db->table('leads')->selectMin('created_at')->get()->getRow('created_at');
        $this->assertGreaterThan(strtotime('-190 days'), strtotime($oldest));
    }

    public function testSeederDealAktivitasDanTugas(): void
    {
        $this->seed(DealSeeder::class);
        $this->seed(ActivitySeeder::class);
        $this->seed(TaskSeeder::class);

        $rows   = $this->db->table('deals d')->select('s.name, COUNT(*) AS total')->join('pipeline_stages s', 's.id = d.stage_id')->groupBy('s.name')->get()->getResultArray();
        $counts = array_map('intval', array_column($rows, 'total', 'name'));
        ksort($counts);
        $expected = DealSeeder::DISTRIBUTION;
        ksort($expected);
        $this->assertSame($expected, $counts);

        $showcase = $this->db->table('deals')->where('title', DealSeeder::SHOWCASE)->get()->getRowArray();
        $this->assertSame('485000000.00', $showcase['amount']);
        $this->assertSame($this->masterId('pipeline_stages', 'Negosiasi'), (int) $showcase['stage_id']);
        $this->assertSame(4, $this->db->table('deal_stage_history')->where('deal_id', $showcase['id'])->countAllResults());

        // Deal tertutup lengkap; amount dalam rentang.
        $this->assertSame(0, $this->db->table('deals d')->join('pipeline_stages s', 's.id = d.stage_id')
            ->groupStart()->where('s.is_won', 1)->orWhere('s.is_lost', 1)->groupEnd()->where('d.closed_at', null)->countAllResults());
        $this->assertSame(0, $this->db->table('deals d')->join('pipeline_stages s', 's.id = d.stage_id')
            ->where('s.is_lost', 1)->where('d.lost_reason_id', null)->countAllResults());
        $this->assertSame(0, $this->db->table('deals')->groupStart()->where('amount <', 15000000)->orWhere('amount >', 850000000)->groupEnd()->countAllResults());

        $activities = $this->db->table('activities')->countAllResults();
        $this->assertGreaterThanOrEqual(350, $activities);
        $this->assertLessThanOrEqual(480, $activities);
        $this->seeInDatabase('activities', ['subject' => 'Meeting demo di kantor klien', 'related_id' => $showcase['id']]);

        foreach (DemoData::SALES as $username) {
            $id = $this->user($username)->id;
            $this->assertGreaterThanOrEqual(2, $this->db->table('tasks')->where('assigned_to', $id)->where('status', 'open')
                ->where('due_at >=', date('Y-m-d 00:00:00'))->where('due_at <=', date('Y-m-d 23:59:59'))->countAllResults());
            $this->assertGreaterThanOrEqual(2, $this->db->table('tasks')->where('assigned_to', $id)->where('status', 'open')
                ->where('due_at <', date('Y-m-d 00:00:00'))->countAllResults());
        }

        $this->seeInDatabase('tasks', ['title' => 'Kirim revisi penawaran ke Pak Bambang', 'priority' => 'high', 'related_id' => $showcase['id']]);
    }

    public function testSeederTidakMenduplikasiData(): void
    {
        $this->seed(ProductSeeder::class);
        $this->seed(CompanySeeder::class);
        $this->seed(LeadSeeder::class);

        $this->assertSame(25, $this->db->table('products')->countAllResults());
        $this->assertSame(60, $this->db->table('companies')->countAllResults());
        $this->assertSame(120, $this->db->table('leads')->countAllResults());
    }
}
