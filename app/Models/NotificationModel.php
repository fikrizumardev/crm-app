<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table         = 'notifications';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'type', 'message', 'url', 'read_at'];

    public function unreadCount(int $userId): int
    {
        return $this->where('user_id', $userId)->where('read_at', null)->countAllResults();
    }

    /**
     * Notifikasi terbaru milik user.
     *
     * @return list<array<string, mixed>>
     */
    public function latestFor(int $userId, int $limit = 10): array
    {
        return $this->where('user_id', $userId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC')->findAll($limit);
    }

    public function markRead(int $id, int $userId): void
    {
        $this->builder()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('read_at', null)
            ->update(['read_at' => Time::now()->toDateTimeString()]);
    }

    public function markAllRead(int $userId): void
    {
        $this->builder()
            ->where('user_id', $userId)
            ->where('read_at', null)
            ->update(['read_at' => Time::now()->toDateTimeString()]);
    }

    /**
     * Apakah sudah ada notifikasi dengan tipe ini untuk user hari ini.
     */
    public function existsToday(int $userId, string $type): bool
    {
        return $this->where('user_id', $userId)
            ->where('type', $type)
            ->where('created_at >=', Time::today()->toDateTimeString())
            ->countAllResults() > 0;
    }
}
