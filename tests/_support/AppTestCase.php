<?php

namespace Tests\Support;

use App\Database\Seeds\MasterDataSeeder;
use App\Database\Seeds\SettingSeeder;
use App\Database\Seeds\UserSeeder;
use App\Entities\User;
use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters;
use Config\Services;

/**
 * Base test: database MySQL `crm_app_test` dimigrasi sekali per proses,
 * lalu setiap test dimulai dari tabel kosong + setting, master data, dan 9 akun demo.
 */
abstract class AppTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    /**
     * Aktifkan filter CSRF global (default dimatikan agar test form lebih ringkas).
     */
    protected bool $withCsrf = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Satu transaksi untuk reset + seed jauh lebih cepat daripada autocommit per query.
        $this->db->transBegin();
        $this->truncateAll();
        $this->seed(SettingSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(UserSeeder::class);
        $this->db->transCommit();
        SettingModel::clearCache();

        if (! $this->withCsrf) {
            $filters                    = config(Filters::class);
            $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));
        }

        Services::resetSingle('auth');
    }

    /**
     * Kosongkan semua tabel aplikasi. DELETE jauh lebih cepat daripada TRUNCATE untuk tabel kecil;
     * test tidak bergantung pada nilai auto increment.
     */
    protected function truncateAll(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->db->listTables() as $table) {
            if ($table !== 'migrations') {
                $this->db->table($table)->emptyTable();
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function user(string $username): User
    {
        $user = model(UserModel::class)->findByUsername($username);
        $this->assertNotNull($user, "User {$username} tidak ditemukan");

        return $user;
    }

    /**
     * ID master data berdasarkan nama (auto increment tidak di-reset antar test).
     */
    protected function masterId(string $table, string $name): int
    {
        return (int) $this->db->table($table)->where('name', $name)->get()->getRow('id');
    }

    /**
     * Buat lead langsung di database.
     *
     * @param array<string, mixed> $data
     */
    protected function makeLead(string $owner, array $data = []): int
    {
        $this->db->table('leads')->insert(array_merge([
            'name'       => 'Lead ' . $owner,
            'status'     => 'new',
            'owner_id'   => $this->user($owner)->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function makeCompany(string $owner, array $data = []): int
    {
        $this->db->table('companies')->insert(array_merge([
            'name'       => 'PT Uji ' . $owner,
            'owner_id'   => $this->user($owner)->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));

        return (int) $this->db->insertID();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function makeContact(string $owner, array $data = []): int
    {
        $this->db->table('contacts')->insert(array_merge([
            'first_name' => 'Kontak',
            'last_name'  => $owner,
            'owner_id'   => $this->user($owner)->id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $data));

        return (int) $this->db->insertID();
    }

    /**
     * Buat deal di tahap tertentu (berdasarkan nama tahap) beserta riwayat awal.
     *
     * @param array<string, mixed> $data
     */
    protected function makeDeal(string $owner, string $stage = 'Prospek', array $data = []): int
    {
        $stageRow = $this->db->table('pipeline_stages')->where('name', $stage)->get()->getRowArray();
        $closed   = (int) $stageRow['is_won'] === 1 || (int) $stageRow['is_lost'] === 1;

        $this->db->table('deals')->insert(array_merge([
            'title'          => 'Deal ' . $owner,
            'stage_id'       => $stageRow['id'],
            'amount'         => 100000000,
            'probability'    => $stageRow['probability'],
            'closed_at'      => $closed ? date('Y-m-d H:i:s') : null,
            'lost_reason_id' => (int) $stageRow['is_lost'] === 1 ? $this->masterId('lost_reasons', 'Harga') : null,
            'owner_id'       => $this->user($owner)->id,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ], $data));

        $id = (int) $this->db->insertID();
        $this->db->table('deal_stage_history')->insert([
            'deal_id'     => $id,
            'to_stage_id' => $stageRow['id'],
            'changed_by'  => $this->user($owner)->id,
            'changed_at'  => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function makeTask(string $assignee, array $data = []): int
    {
        $this->db->table('tasks')->insert(array_merge([
            'title'       => 'Tugas ' . $assignee,
            'due_at'      => date('Y-m-d 10:00:00'),
            'priority'    => 'medium',
            'status'      => 'open',
            'assigned_to' => $this->user($assignee)->id,
            'created_by'  => $this->user($assignee)->id,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], $data));

        return (int) $this->db->insertID();
    }

    /**
     * Error validasi dari flash session request terakhir.
     *
     * @return array<string, string>
     */
    protected function flashErrors(): array
    {
        return $_SESSION['errors'] ?? [];
    }

    /**
     * Jalankan request berikutnya sebagai user tertentu.
     *
     * @return $this
     */
    protected function actingAs(string $username): static
    {
        $user = $this->user($username);
        service('auth')->refresh();

        return $this->withSession([
            'user_id'  => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'name'     => $user->name,
        ]);
    }
}
