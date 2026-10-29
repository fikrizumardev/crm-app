<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class LoginAttemptModel extends Model
{
    protected $table         = 'login_attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['username', 'ip_address', 'success', 'user_agent'];

    public function record(string $username, string $ip, bool $success, ?string $userAgent): void
    {
        $this->insert([
            'username'   => mb_substr($username, 0, 100),
            'ip_address' => mb_substr($ip, 0, 45),
            'success'    => $success ? 1 : 0,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }

    /**
     * Waktu percobaan gagal terbaru (maks. $limit) untuk username + IP dalam rentang waktu,
     * dihitung sejak login sukses terakhir. Urut dari yang terbaru.
     *
     * @return list<Time>
     */
    public function recentFailures(string $username, string $ip, int $windowSeconds, int $limit): array
    {
        $since = Time::now()->subSeconds($windowSeconds)->toDateTimeString();

        $lastSuccess = $this->builder()
            ->selectMax('created_at')
            ->where(['username' => $username, 'ip_address' => $ip, 'success' => 1])
            ->get()
            ->getRow('created_at');

        if ($lastSuccess !== null && $lastSuccess > $since) {
            $since = $lastSuccess;
        }

        $rows = $this->builder()
            ->select('created_at')
            ->where(['username' => $username, 'ip_address' => $ip, 'success' => 0])
            ->where('created_at >', $since)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): Time => Time::parse($row['created_at']), $rows);
    }
}
