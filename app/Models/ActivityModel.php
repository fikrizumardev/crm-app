<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Aktivitas polimorfik ke lead, kontak, perusahaan, atau deal (PRD §4 M7).
 */
class ActivityModel extends Model
{
    /**
     * Jenis aktivitas → [label, ikon, warna lingkaran].
     */
    public const TYPES = [
        'call'     => ['label' => 'Telepon', 'icon' => 'phone', 'class' => 'bg-sky-100 text-sky-700'],
        'meeting'  => ['label' => 'Meeting', 'icon' => 'user-group', 'class' => 'bg-indigo-100 text-indigo-700'],
        'email'    => ['label' => 'Email', 'icon' => 'envelope', 'class' => 'bg-amber-100 text-amber-700'],
        'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'chat-bubble-left-right', 'class' => 'bg-green-100 text-green-700'],
        'visit'    => ['label' => 'Kunjungan', 'icon' => 'map-pin', 'class' => 'bg-rose-100 text-rose-700'],
        'note'     => ['label' => 'Catatan', 'icon' => 'document-text', 'class' => 'bg-slate-100 text-slate-700'],
    ];

    /**
     * Entitas yang boleh ditautkan → [model, label, path URL].
     */
    public const RELATED = [
        'lead'    => ['model' => LeadModel::class, 'label' => 'Lead', 'path' => 'leads'],
        'contact' => ['model' => ContactModel::class, 'label' => 'Kontak', 'path' => 'contacts'],
        'company' => ['model' => CompanyModel::class, 'label' => 'Perusahaan', 'path' => 'companies'],
        'deal'    => ['model' => DealModel::class, 'label' => 'Deal', 'path' => 'deals'],
    ];

    protected $table          = 'activities';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['type', 'subject', 'description', 'related_type', 'related_id', 'activity_at', 'user_id'];
    protected $validationRules = [
        'type'         => ['label' => 'Jenis aktivitas', 'rules' => 'required|in_list[call,meeting,email,whatsapp,visit,note]'],
        'subject'      => ['label' => 'Ringkasan', 'rules' => 'required|max_length[200]'],
        'description'  => ['label' => 'Detail', 'rules' => 'permit_empty|max_length[5000]'],
        'related_type' => ['label' => 'Tautan', 'rules' => 'required|in_list[lead,contact,company,deal]'],
        'related_id'   => ['label' => 'Tautan', 'rules' => 'required|is_natural_no_zero'],
        'activity_at'  => ['label' => 'Waktu', 'rules' => 'required|valid_date[Y-m-d H:i:s]'],
        'user_id'      => ['label' => 'User', 'rules' => 'required|is_natural_no_zero'],
    ];

    /**
     * @return $this
     */
    private function withUser(): static
    {
        return $this->select('activities.*, users.name AS user_name')
            ->join('users', 'users.id = activities.user_id', 'left')
            ->orderBy('activities.activity_at', 'DESC')
            ->orderBy('activities.id', 'DESC');
    }

    /**
     * Aktivitas untuk satu record.
     *
     * @return list<array<string, mixed>>
     */
    public function forRecord(string $type, int $id, ?string $activityType = null, int $limit = 100): array
    {
        $this->withUser()->where('related_type', $type)->where('related_id', $id);

        if ($activityType !== null) {
            $this->where('activities.type', $activityType);
        }

        return $this->findAll($limit);
    }

    /**
     * Aktivitas perusahaan beserta aktivitas kontak & deal di dalamnya.
     *
     * @return list<array<string, mixed>>
     */
    public function forCompany(int $companyId, int $limit = 100): array
    {
        return $this->withUser()
            ->groupStart()
            ->groupStart()->where('related_type', 'company')->where('related_id', $companyId)->groupEnd()
            ->orGroupStart()->where('related_type', 'contact')
            ->whereIn('related_id', static fn (BaseBuilder $b) => $b->select('id')->from('contacts')->where('company_id', $companyId)->where('deleted_at', null))
            ->groupEnd()
            ->orGroupStart()->where('related_type', 'deal')
            ->whereIn('related_id', static fn (BaseBuilder $b) => $b->select('id')->from('deals')->where('company_id', $companyId)->where('deleted_at', null))
            ->groupEnd()
            ->groupEnd()
            ->findAll($limit);
    }

    /**
     * Label & URL record tertaut untuk banyak aktivitas/tugas sekaligus (hindari N+1).
     *
     * @param list<array<string, mixed>> $rows baris dengan related_type & related_id
     *
     * @return array<string, array{label: string, name: string, url: string}> kunci "type:id"
     */
    public static function relatedLabels(array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            if (isset(self::RELATED[$row['related_type'] ?? ''])) {
                $ids[$row['related_type']][] = (int) $row['related_id'];
            }
        }

        $db     = db_connect();
        $result = [];

        foreach ($ids as $type => $list) {
            $table  = ['lead' => 'leads', 'contact' => 'contacts', 'company' => 'companies', 'deal' => 'deals'][$type];
            $select = match ($type) {
                'contact' => "id, CONCAT(first_name, ' ', COALESCE(last_name, '')) AS name",
                'deal'    => 'id, title AS name',
                default   => 'id, name',
            };

            foreach ($db->table($table)->select($select, false)->whereIn('id', array_unique($list))->get()->getResultArray() as $row) {
                $result[$type . ':' . $row['id']] = [
                    'label' => self::RELATED[$type]['label'],
                    'name'  => trim($row['name']),
                    'url'   => site_url(self::RELATED[$type]['path'] . '/' . $row['id']),
                ];
            }
        }

        return $result;
    }
}
