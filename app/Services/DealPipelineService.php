<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\DealModel;
use App\Models\DealStageHistoryModel;
use App\Models\LostReasonModel;
use App\Models\PipelineStageModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Throwable;

/**
 * Perpindahan tahap deal: validasi aturan, update deal, riwayat tahap, dan log audit
 * dalam satu transaksi. Dipakai form edit, tombol pindah tahap, dan Kanban (PRD §4 M6).
 */
class DealPipelineService
{
    public function __construct(
        private readonly DealModel $deals,
        private readonly PipelineStageModel $stages,
        private readonly DealStageHistoryModel $history,
        private readonly LostReasonModel $lostReasons,
        private readonly AuditLogger $audit,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * Kolom yang ditentukan oleh tahap: probabilitas, tanggal tutup, alasan kalah.
     *
     * @param array<string, mixed> $stage
     *
     * @return array{probability: int, closed_at: string|null, lost_reason_id: int|null}
     *
     * @throws ValidationFailedException
     */
    public function stageRules(array $stage, ?int $lostReasonId, ?int $probability, ?string $closedAt = null): array
    {
        $isWon  = (int) $stage['is_won'] === 1;
        $isLost = (int) $stage['is_lost'] === 1;

        if ($isLost && ($lostReasonId === null || $this->lostReasons->find($lostReasonId) === null)) {
            throw new ValidationFailedException(['lost_reason_id' => 'Pilih alasan kalah sebelum memindahkan deal ke Lost.']);
        }

        if ($probability !== null && ($probability < 0 || $probability > 100)) {
            throw new ValidationFailedException(['probability' => 'Probabilitas harus antara 0 dan 100.']);
        }

        return [
            'probability'    => $isWon ? 100 : ($isLost ? 0 : ($probability ?? (int) $stage['probability'])),
            'closed_at'      => $isWon || $isLost ? ($closedAt ?? Time::now()->toDateTimeString()) : null,
            'lost_reason_id' => $isLost ? $lostReasonId : null,
        ];
    }

    /**
     * Pindahkan deal ke tahap lain. Mengembalikan deal terbaru (dengan relasi).
     *
     * @param array<string, mixed> $deal
     *
     * @return array<string, mixed>
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function moveToStage(array $deal, int $stageId, User $actor, ?int $lostReasonId = null, ?int $probability = null): array
    {
        if (! $this->deals->canEdit($actor, $deal)) {
            throw new BusinessRuleException('Anda tidak berhak mengubah deal ini.');
        }

        $stage = $this->stages->find($stageId);

        if ($stage === null) {
            throw new ValidationFailedException(['stage_id' => 'Tahap tidak ditemukan.']);
        }

        $sameStage = (int) $deal['stage_id'] === $stageId;
        $changes   = ['stage_id' => $stageId] + $this->stageRules(
            $stage,
            $lostReasonId,
            $probability,
            $sameStage ? $deal['closed_at'] : null,
        );

        $old = array_intersect_key($deal, $changes);

        if ($sameStage && array_map('strval', $old) == array_map('strval', $changes)) {
            return $this->deals->withRelations()->find($deal['id']);
        }

        $this->db->transBegin();

        try {
            $this->deals->skipValidation()->update($deal['id'], $changes);
            $this->deals->skipValidation(false);

            if (! $sameStage) {
                $this->history->insert([
                    'deal_id'       => $deal['id'],
                    'from_stage_id' => $deal['stage_id'],
                    'to_stage_id'   => $stageId,
                    'changed_by'    => $actor->id,
                    'changed_at'    => Time::now()->toDateTimeString(),
                ]);
            }

            $this->audit->log('stage_change', 'deal', $deal['id'], $old, $changes);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return $this->deals->withRelations()->find($deal['id']);
    }
}
