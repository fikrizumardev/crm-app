<?php

use App\Models\TaskModel;
use CodeIgniter\I18n\Time;
use Tests\Support\AppTestCase;

/**
 * Aktivitas polimorfik, tugas, dan notifikasi (T3.05–T3.07).
 *
 * @internal
 */
final class ActivityTaskTest extends AppTestCase
{
    public function testCatatAktivitasPadaLeadMilikSendiri(): void
    {
        $leadId = $this->makeLead('andi.saputra');

        $result = $this->actingAs('andi.saputra')->post('activities', [
            'type'         => 'call',
            'subject'      => 'Telepon perkenalan',
            'description'  => 'Minta company profile.',
            'related_type' => 'lead',
            'related_id'   => (string) $leadId,
            'activity_at'  => date('Y-m-d') . 'T10:15',
        ]);

        $result->assertRedirectTo(site_url('leads/' . $leadId));
        $this->seeInDatabase('activities', [
            'related_type' => 'lead',
            'related_id'   => $leadId,
            'subject'      => 'Telepon perkenalan',
            'activity_at'  => date('Y-m-d') . ' 10:15:00',
            'user_id'      => $this->user('andi.saputra')->id,
        ]);

        $this->actingAs('andi.saputra')->get('leads/' . $leadId)->assertSee('Telepon perkenalan');
    }

    public function testAktivitasDiRecordOrangLainDitolak(): void
    {
        $dealId = $this->makeDeal('siti.rahma');

        $this->actingAs('andi.saputra')->post('activities', [
            'type' => 'note', 'subject' => 'Iseng', 'related_type' => 'deal', 'related_id' => (string) $dealId,
        ]);

        $this->assertArrayHasKey('related_id', $this->flashErrors());
        $this->assertSame(0, $this->db->table('activities')->countAllResults());
    }

    public function testTipeTautanDiluarWhitelistDitolak(): void
    {
        $this->actingAs('admin')->post('activities', [
            'type' => 'note', 'subject' => 'Coba', 'related_type' => 'users', 'related_id' => '1',
        ]);

        $this->assertSame(0, $this->db->table('activities')->countAllResults());
    }

    public function testAktivitasKontakTampilDiTimelinePerusahaan(): void
    {
        $companyId = $this->makeCompany('andi.saputra');
        $contactId = $this->makeContact('andi.saputra', ['company_id' => $companyId]);

        $this->actingAs('andi.saputra')->post('activities', [
            'type' => 'visit', 'subject' => 'Kunjungan ke gudang', 'related_type' => 'contact', 'related_id' => (string) $contactId,
        ]);

        $this->actingAs('andi.saputra')->get('companies/' . $companyId)->assertSee('Kunjungan ke gudang');
    }

    public function testHapusAktivitasHanyaPencatatAtauAdmin(): void
    {
        $leadId = $this->makeLead('andi.saputra');
        $this->actingAs('andi.saputra')->post('activities', ['type' => 'note', 'subject' => 'Catatan Andi', 'related_type' => 'lead', 'related_id' => (string) $leadId]);
        $id = (int) $this->db->table('activities')->get()->getRow('id');

        $this->actingAs('dewi.lestari')->post("activities/{$id}/delete");
        $this->seeInDatabase('activities', ['id' => $id, 'deleted_at' => null]);

        $this->actingAs('admin')->post("activities/{$id}/delete");
        $this->assertNotNull($this->db->table('activities')->where('id', $id)->get()->getRow('deleted_at'));
    }

    public function testSalesMembuatTugasUntukDirinyaSendiri(): void
    {
        $dealId = $this->makeDeal('andi.saputra');

        $this->actingAs('andi.saputra')->post('tasks', [
            'title'        => 'Kirim revisi penawaran',
            'due_at'       => date('Y-m-d', strtotime('+1 day')) . 'T10:00',
            'priority'     => 'high',
            'assigned_to'  => (string) $this->user('siti.rahma')->id,
            'related_type' => 'deal',
            'related_id'   => (string) $dealId,
        ])->assertRedirectTo(site_url('deals/' . $dealId));

        $this->seeInDatabase('tasks', ['title' => 'Kirim revisi penawaran', 'assigned_to' => $this->user('andi.saputra')->id, 'related_id' => $dealId]);
        $this->assertSame(0, $this->db->table('notifications')->countAllResults());
    }

    public function testManagerMenugaskanSalesTimDanNotifikasiTerkirim(): void
    {
        $this->actingAs('dewi.lestari')->post('tasks', [
            'title'       => 'Telepon klien prioritas',
            'due_at'      => date('Y-m-d') . 'T15:00',
            'priority'    => 'medium',
            'assigned_to' => (string) $this->user('budi.santoso')->id,
        ])->assertRedirectTo(site_url('tasks'));

        $this->seeInDatabase('tasks', ['title' => 'Telepon klien prioritas', 'assigned_to' => $this->user('budi.santoso')->id, 'created_by' => $this->user('dewi.lestari')->id]);
        $this->seeInDatabase('notifications', ['user_id' => $this->user('budi.santoso')->id, 'type' => 'task_assigned', 'read_at' => null]);
    }

    public function testManagerTidakBisaMenugaskanTimLain(): void
    {
        $this->actingAs('dewi.lestari')->post('tasks', [
            'title' => 'Salah tim', 'due_at' => date('Y-m-d') . 'T15:00', 'priority' => 'low', 'assigned_to' => (string) $this->user('putri.ayu')->id,
        ]);

        $this->assertArrayHasKey('assigned_to', $this->flashErrors());
        $this->assertSame(0, $this->db->table('tasks')->countAllResults());
    }

    public function testTabTugas(): void
    {
        $this->makeTask('andi.saputra', ['title' => 'Tugas Hari Ini', 'due_at' => date('Y-m-d 16:00:00')]);
        $this->makeTask('andi.saputra', ['title' => 'Tugas Terlambat', 'due_at' => date('Y-m-d 10:00:00', strtotime('-2 days'))]);
        $this->makeTask('andi.saputra', ['title' => 'Tugas Selesai', 'status' => 'done', 'completed_at' => date('Y-m-d H:i:s')]);
        $this->makeTask('siti.rahma', ['title' => 'Tugas Siti']);

        $counts = model(TaskModel::class)->tabCounts($this->user('andi.saputra'));
        $this->assertSame(1, $counts['today']);
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(1, $counts['done']);
        $this->assertGreaterThanOrEqual(1, $counts['week']);

        $today = $this->actingAs('andi.saputra')->get('tasks?tab=today');
        $today->assertSee('Tugas Hari Ini');
        $today->assertDontSee('Tugas Terlambat');
        $today->assertDontSee('Tugas Siti');

        $overdue = $this->actingAs('andi.saputra')->get('tasks?tab=overdue');
        $overdue->assertSee('Tugas Terlambat');
        $overdue->assertSee('Terlambat ·');
    }

    public function testTandaiSelesaiDanBukaKembali(): void
    {
        $id = $this->makeTask('andi.saputra');

        $this->actingAs('andi.saputra')->post("tasks/{$id}/done")->assertRedirectTo(site_url('tasks'));
        $task = $this->db->table('tasks')->where('id', $id)->get()->getRowArray();
        $this->assertSame('done', $task['status']);
        $this->assertNotNull($task['completed_at']);

        $this->actingAs('andi.saputra')->post("tasks/{$id}/reopen");
        $this->seeInDatabase('tasks', ['id' => $id, 'status' => 'open', 'completed_at' => null]);
    }

    public function testTugasOrangLainTidakBisaDiselesaikan(): void
    {
        $id = $this->makeTask('siti.rahma');

        $this->actingAs('andi.saputra')->post("tasks/{$id}/done")->assertStatus(404);
        $this->seeInDatabase('tasks', ['id' => $id, 'status' => 'open']);

        $this->actingAs('dewi.lestari')->post("tasks/{$id}/done");
        $this->seeInDatabase('tasks', ['id' => $id, 'status' => 'done']);
    }

    public function testRingkasanTugasHariIniSaatLoginSekaliSehari(): void
    {
        $this->makeTask('andi.saputra', ['due_at' => date('Y-m-d 17:00:00')]);
        $this->makeTask('andi.saputra', ['due_at' => date('Y-m-d 18:00:00')]);

        $this->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);
        $this->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);

        $rows = $this->db->table('notifications')->where('user_id', $this->user('andi.saputra')->id)->where('type', 'task_due')->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('2 tugas', $rows[0]['message']);
    }

    public function testNotifikasiReassignLead(): void
    {
        $leadId = $this->makeLead('andi.saputra', ['name' => 'Lead Dialihkan']);

        $this->actingAs('dewi.lestari')->post('leads/' . $leadId, [
            'name' => 'Lead Dialihkan', 'status' => 'new', 'owner_id' => (string) $this->user('siti.rahma')->id,
        ]);

        $this->seeInDatabase('leads', ['id' => $leadId, 'owner_id' => $this->user('siti.rahma')->id]);
        $note = $this->db->table('notifications')->where('user_id', $this->user('siti.rahma')->id)->get()->getRowArray();
        $this->assertSame('reassigned', $note['type']);
        $this->assertStringContainsString('Lead Dialihkan', $note['message']);
        $this->assertSame(site_url('leads/' . $leadId), $note['url']);
    }

    public function testEndpointNotifikasi(): void
    {
        $andi = $this->user('andi.saputra')->id;
        service('notifications')->notify($andi, 'test', 'Halo Andi', site_url('tasks'));
        service('notifications')->notify($this->user('siti.rahma')->id, 'test', 'Halo Siti', site_url('tasks'));
        $andiNote = (int) $this->db->table('notifications')->where('user_id', $andi)->get()->getRow('id');
        $sitiNote = (int) $this->db->table('notifications')->where('user_id !=', $andi)->get()->getRow('id');

        $json = json_decode($this->actingAs('andi.saputra')->get('notifications')->getJSON(), true);
        $this->assertSame(1, $json['unread']);
        $this->assertSame('Halo Andi', $json['items'][0]['message']);

        $this->actingAs('andi.saputra')->get('notifications/' . $sitiNote)->assertStatus(404);

        $this->actingAs('andi.saputra')->get('notifications/' . $andiNote)->assertRedirectTo(site_url('tasks'));
        $this->assertNotNull($this->db->table('notifications')->where('id', $andiNote)->get()->getRow('read_at'));

        service('notifications')->notify($andi, 'test', 'Kedua', null);
        $this->actingAs('andi.saputra')->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('notifications/read-all')->assertOK();
        $this->assertSame(0, model(\App\Models\NotificationModel::class)->unreadCount($andi));
        $this->seeInDatabase('notifications', ['user_id' => $this->user('siti.rahma')->id, 'read_at' => null]);
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }
}
