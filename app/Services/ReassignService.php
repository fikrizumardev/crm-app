<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\LeadModel;
use App\Models\UserModel;

/**
 * Alihkan owner lead/deal/perusahaan/kontak (PRD §3): admin untuk semua data,
 * manager hanya dalam timnya. Owner baru mendapat notifikasi ringkas.
 */
class ReassignService
{
    /**
     * Entitas → [model, label, path].
     */
    public const ENTITIES = [
        'leads'     => ['model' => LeadModel::class, 'label' => 'lead', 'entity' => 'lead'],
        'deals'     => ['model' => DealModel::class, 'label' => 'deal', 'entity' => 'deal'],
        'companies' => ['model' => CompanyModel::class, 'label' => 'perusahaan', 'entity' => 'company'],
        'contacts'  => ['model' => ContactModel::class, 'label' => 'kontak', 'entity' => 'contact'],
    ];

    public function __construct(
        private readonly UserModel $users,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @param list<int|string> $ids
     *
     * @return int jumlah record yang dialihkan
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function reassign(string $path, array $ids, mixed $ownerId, User $actor): int
    {
        if (! isset(self::ENTITIES[$path]) || $actor->isSales()) {
            throw new BusinessRuleException('Anda tidak berhak mengalihkan data.');
        }

        $ownerId = ctype_digit((string) $ownerId) ? (int) $ownerId : 0;

        if (! array_key_exists($ownerId, $this->users->ownerOptions($actor))) {
            throw new ValidationFailedException(['owner_id' => 'Pilih owner baru dalam tim Anda.']);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            throw new ValidationFailedException(['ids' => 'Pilih minimal satu data.']);
        }

        $config  = self::ENTITIES[$path];
        $model   = model($config['model']);
        $records = $model->visibleTo($actor)->whereIn($model->getTable() . '.id', $ids)->findAll();
        $moved   = 0;

        foreach ($records as $record) {
            if ((int) $record['owner_id'] === $ownerId) {
                continue;
            }

            $model->skipValidation()->update($record['id'], ['owner_id' => $ownerId]);
            $this->audit->log('reassign', $config['entity'], $record['id'], ['owner_id' => (int) $record['owner_id']], ['owner_id' => $ownerId]);
            $moved++;
        }

        $model->skipValidation(false);

        if ($moved > 0 && $ownerId !== $actor->id) {
            $this->notifications->notify(
                $ownerId,
                NotificationService::TYPE_REASSIGNED,
                sprintf('%s mengalihkan %d %s kepada Anda.', $actor->name, $moved, $config['label']),
                site_url($path . '?owner=' . $ownerId),
            );
        }

        return $moved;
    }
}
