<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\UserModel;

/**
 * Log audit: siapa mengubah apa dan kapan (khusus admin).
 */
class AuditLogController extends BaseController
{
    public function index(): string
    {
        $filters = $this->request->getGet(['user', 'entity', 'action', 'from', 'to']);
        $logs    = model(AuditLogModel::class);

        $logs->select('audit_logs.*, users.name AS user_name, users.username')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.created_at', 'DESC')
            ->orderBy('audit_logs.id', 'DESC');

        if (ctype_digit((string) ($filters['user'] ?? ''))) {
            $logs->where('audit_logs.user_id', (int) $filters['user']);
        }

        foreach (['entity', 'action'] as $key) {
            if (($filters[$key] ?? '') !== '' && preg_match('/^[a-z_]+$/', (string) $filters[$key]) === 1) {
                $logs->where('audit_logs.' . $key, $filters[$key]);
            }
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters[$key] ?? '')) === 1) {
                $logs->where('audit_logs.created_at ' . $operator, $filters[$key] . ($key === 'from' ? ' 00:00:00' : ' 23:59:59'));
            }
        }

        $users = [];

        foreach (model(UserModel::class)->orderBy('name')->findAll() as $user) {
            $users[$user->id] = $user->name;
        }

        $db = db_connect();

        return view('admin/audit-logs', [
            'title'    => 'Log Audit',
            'logs'     => $logs->paginate(50),
            'pager'    => $logs->pager,
            'filters'  => $filters,
            'users'    => $users,
            'entities' => array_column($db->table('audit_logs')->select('entity')->distinct()->orderBy('entity')->get()->getResultArray(), 'entity'),
            'actions'  => array_column($db->table('audit_logs')->select('action')->distinct()->orderBy('action')->get()->getResultArray(), 'action'),
        ]);
    }
}
