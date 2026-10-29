<?php

namespace App\Models;

use CodeIgniter\Model;

class PipelineStageModel extends Model
{
    protected $table         = 'pipeline_stages';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'probability', 'sort_order', 'is_won', 'is_lost'];

    /**
     * Semua tahap urut `sort_order`.
     *
     * @return list<array<string, mixed>>
     */
    public function ordered(): array
    {
        return $this->orderBy('sort_order')->orderBy('id')->findAll();
    }

    /**
     * Tahap yang masih terbuka (bukan Won/Lost).
     *
     * @return list<array<string, mixed>>
     */
    public function openStages(): array
    {
        return $this->where('is_won', 0)->where('is_lost', 0)->orderBy('sort_order')->findAll();
    }

    /**
     * @return array<int, string>
     */
    public function openStageOptions(): array
    {
        return array_column($this->openStages(), 'name', 'id');
    }
}
