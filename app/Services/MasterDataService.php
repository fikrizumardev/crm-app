<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\IndustryModel;
use App\Models\LeadSourceModel;
use App\Models\LostReasonModel;
use App\Models\PipelineStageModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Model;
use Throwable;

/**
 * Master data yang dikelola admin (PRD §4 M9). Data yang masih dipakai tidak bisa dihapus.
 */
class MasterDataService
{
    /**
     * Jenis master sederhana → [model, tabel, label, pemakaian (tabel.kolom)].
     */
    public const TYPES = [
        'sources'      => ['model' => LeadSourceModel::class, 'table' => 'lead_sources', 'label' => 'Sumber lead', 'usage' => [['leads', 'source_id']]],
        'industries'   => ['model' => IndustryModel::class, 'table' => 'industries', 'label' => 'Industri', 'usage' => [['companies', 'industry_id']]],
        'lost-reasons' => ['model' => LostReasonModel::class, 'table' => 'lost_reasons', 'label' => 'Alasan kalah', 'usage' => [['deals', 'lost_reason_id']]],
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Semua item satu jenis beserta jumlah pemakaiannya.
     *
     * @return list<array<string, mixed>>
     */
    public function items(string $type): array
    {
        $config = self::TYPES[$type];
        $rows   = $this->db->table($config['table'])->orderBy('name')->get()->getResultArray();
        $usage  = $this->usageCounts($config['usage']);

        return array_map(static fn (array $row): array => $row + ['usage' => $usage[(int) $row['id']] ?? 0], $rows);
    }

    /**
     * Tahap pipeline urut beserta jumlah deal yang memakainya.
     *
     * @return list<array<string, mixed>>
     */
    public function stages(): array
    {
        $usage = $this->usageCounts([['deals', 'stage_id']]);

        return array_map(
            static fn (array $row): array => $row + ['usage' => $usage[(int) $row['id']] ?? 0],
            model(PipelineStageModel::class)->ordered(),
        );
    }

    /**
     * @throws ValidationFailedException
     */
    public function save(string $type, ?int $id, string $name): void
    {
        $config = self::TYPES[$type];
        $name   = trim($name);
        $this->assertUniqueName($config['table'], $name, $id, $config['label']);

        $model = model($config['model']);
        $old   = $id === null ? null : $model->find($id);

        if ($id === null) {
            $id = (int) $model->insert(['name' => $name]);
            $this->audit->log('create', $config['table'], $id, null, ['name' => $name]);
        } else {
            $model->update($id, ['name' => $name]);
            $this->audit->log('update', $config['table'], $id, $old, ['name' => $name]);
        }
    }

    /**
     * @throws BusinessRuleException
     */
    public function delete(string $type, int $id): void
    {
        $config = self::TYPES[$type];
        $row    = $this->db->table($config['table'])->where('id', $id)->get()->getRowArray();

        if ($row === null) {
            return;
        }

        $used = $this->usageCounts($config['usage'])[$id] ?? 0;

        if ($used > 0) {
            throw new BusinessRuleException(sprintf('%s "%s" masih dipakai %d data sehingga tidak bisa dihapus.', $config['label'], $row['name'], $used));
        }

        model($config['model'])->delete($id);
        $this->audit->log('delete', $config['table'], $id, $row, null);
    }

    /**
     * Simpan tahap pipeline. Tahap Won/Lost probabilitasnya tetap 100%/0%.
     *
     * @throws ValidationFailedException
     */
    public function saveStage(?int $id, string $name, mixed $probability): void
    {
        $name   = trim($name);
        $stages = model(PipelineStageModel::class);
        $old    = $id === null ? null : $stages->find($id);

        $this->assertUniqueName('pipeline_stages', $name, $id, 'Tahap');

        if (! ctype_digit(trim((string) $probability)) || (int) $probability > 100) {
            throw new ValidationFailedException(['probability' => 'Probabilitas harus angka 0–100.']);
        }

        $probability = (int) $probability;

        if ($old !== null && ((int) $old['is_won'] === 1 || (int) $old['is_lost'] === 1)) {
            $probability = (int) $old['is_won'] === 1 ? 100 : 0;
        }

        if ($old === null) {
            $id = (int) $stages->insert(['name' => $name, 'probability' => $probability, 'sort_order' => 0, 'is_won' => 0, 'is_lost' => 0]);
            $this->normalizeOrder(array_column($stages->openStages(), 'id'));
            $this->audit->log('create', 'pipeline_stages', $id, null, ['name' => $name, 'probability' => $probability]);

            return;
        }

        $stages->update($id, ['name' => $name, 'probability' => $probability]);
        $this->audit->log('update', 'pipeline_stages', $id, $old, ['name' => $name, 'probability' => $probability]);
    }

    /**
     * @throws BusinessRuleException
     */
    public function deleteStage(int $id): void
    {
        $stage = model(PipelineStageModel::class)->find($id);

        if ($stage === null) {
            return;
        }

        if ((int) $stage['is_won'] === 1 || (int) $stage['is_lost'] === 1) {
            throw new BusinessRuleException('Tahap Won dan Lost adalah tahap sistem dan tidak bisa dihapus.');
        }

        $used = (int) $this->db->table('deals')->where('stage_id', $id)->countAllResults()
            + (int) $this->db->table('deal_stage_history')->groupStart()->where('from_stage_id', $id)->orWhere('to_stage_id', $id)->groupEnd()->countAllResults();

        if ($used > 0) {
            throw new BusinessRuleException(sprintf('Tahap "%s" masih dipakai deal atau riwayat tahap sehingga tidak bisa dihapus.', $stage['name']));
        }

        if (count(model(PipelineStageModel::class)->openStages()) <= 1) {
            throw new BusinessRuleException('Minimal harus ada satu tahap terbuka.');
        }

        model(PipelineStageModel::class)->delete($id);
        $this->normalizeOrder(array_column(model(PipelineStageModel::class)->openStages(), 'id'));
        $this->audit->log('delete', 'pipeline_stages', $id, $stage, null);
    }

    /**
     * Urutan baru tahap terbuka; Won dan Lost selalu di akhir.
     *
     * @param list<int|string> $orderedIds
     *
     * @throws ValidationFailedException
     */
    public function reorderStages(array $orderedIds): void
    {
        $openIds = array_map('intval', array_column(model(PipelineStageModel::class)->openStages(), 'id'));
        $ordered = array_map('intval', $orderedIds);

        if (count($ordered) !== count($openIds) || array_diff($openIds, $ordered) !== []) {
            throw new ValidationFailedException(['order' => 'Urutan tahap tidak valid. Muat ulang halaman lalu coba lagi.']);
        }

        $this->normalizeOrder($ordered);
        $this->audit->log('reorder', 'pipeline_stages', null, null, ['order' => $ordered]);
    }

    /**
     * @param list<int|string> $openIds
     */
    private function normalizeOrder(array $openIds): void
    {
        $this->db->transStart();
        $position = 1;

        foreach ($openIds as $id) {
            $this->db->table('pipeline_stages')->where('id', $id)->update(['sort_order' => $position++]);
        }

        $this->db->table('pipeline_stages')->where('is_won', 1)->update(['sort_order' => $position++]);
        $this->db->table('pipeline_stages')->where('is_lost', 1)->update(['sort_order' => $position]);
        $this->db->transComplete();
    }

    /**
     * @throws ValidationFailedException
     */
    private function assertUniqueName(string $table, string $name, ?int $id, string $label): void
    {
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationFailedException(['name' => 'Nama wajib diisi (maks. 100 karakter).']);
        }

        $builder = $this->db->table($table)->where('name', $name);

        if ($id !== null) {
            $builder->where('id !=', $id);
        }

        if ($builder->countAllResults() > 0) {
            throw new ValidationFailedException(['name' => sprintf('%s "%s" sudah ada.', $label, $name)]);
        }
    }

    /**
     * Jumlah pemakaian per ID dari satu atau beberapa kolom referensi (termasuk data terhapus sementara).
     *
     * @param list<array{0: string, 1: string}> $usage
     *
     * @return array<int, int>
     */
    private function usageCounts(array $usage): array
    {
        $counts = [];

        foreach ($usage as [$table, $column]) {
            $rows = $this->db->table($table)->select("{$column} AS ref, COUNT(*) AS total")->where("{$column} IS NOT NULL")->groupBy($column)->get()->getResultArray();

            foreach ($rows as $row) {
                $counts[(int) $row['ref']] = ($counts[(int) $row['ref']] ?? 0) + (int) $row['total'];
            }
        }

        return $counts;
    }
}
