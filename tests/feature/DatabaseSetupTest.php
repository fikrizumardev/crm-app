<?php

use App\Database\Seeds\DatabaseSeeder;
use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class DatabaseSetupTest extends AppTestCase
{
    public function testSembilanBelasTabelTerbentuk(): void
    {
        $tables = array_values(array_diff($this->db->listTables(), ['migrations']));

        $this->assertCount(19, $tables);

        foreach (['users', 'login_attempts', 'settings', 'audit_logs', 'notifications', 'lead_sources', 'industries', 'pipeline_stages', 'lost_reasons', 'companies', 'contacts', 'leads', 'deals', 'deal_stage_history', 'activities', 'tasks', 'products', 'quotations', 'quotation_items'] as $table) {
            $this->assertContains($table, $tables);
        }
    }

    public function testSeederAwalIdempoten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(9, $this->db->table('users')->countAllResults());
        $this->assertSame(7, $this->db->table('lead_sources')->countAllResults());
        $this->assertSame(8, $this->db->table('industries')->countAllResults());
        $this->assertSame(6, $this->db->table('pipeline_stages')->countAllResults());
        $this->assertSame(5, $this->db->table('lost_reasons')->countAllResults());
        $this->seeInDatabase('settings', ['key' => 'company_name', 'value' => 'PT Nusantara Solusi Digital']);
        $this->seeInDatabase('settings', ['key' => 'tax_rate', 'value' => '11']);
    }

    public function testStrukturTimSales(): void
    {
        $dewi   = $this->user('dewi.lestari');
        $hendra = $this->user('hendra.wijaya');

        $this->assertSame(3, $this->db->table('users')->where('manager_id', $dewi->id)->countAllResults());
        $this->assertSame(3, $this->db->table('users')->where('manager_id', $hendra->id)->countAllResults());
        $this->assertSame(6, $this->db->table('users')->where('role', 'sales')->countAllResults());

        $stage = $this->db->table('pipeline_stages')->where('name', 'Won')->get()->getRowArray();
        $this->assertSame('100', (string) $stage['probability']);
        $this->assertSame('1', (string) $stage['is_won']);
    }
}
