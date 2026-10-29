<?php

namespace App\Controllers;

use App\Models\NotificationModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class NotificationController extends BaseController
{
    /**
     * JSON untuk dropdown lonceng.
     */
    public function index(): ResponseInterface
    {
        $user  = $this->currentUser();
        $model = model(NotificationModel::class);

        $items = array_map(static fn (array $row): array => [
            'id'      => (int) $row['id'],
            'message' => $row['message'],
            'url'     => site_url('notifications/' . $row['id']),
            'read'    => $row['read_at'] !== null,
            'time'    => waktu_relatif($row['created_at']),
        ], $model->latestFor($user->id));

        return $this->response->setJSON(['unread' => $model->unreadCount($user->id), 'items' => $items]);
    }

    /**
     * Tandai dibaca lalu buka tautan notifikasi.
     */
    public function open(int $id): RedirectResponse
    {
        $user         = $this->currentUser();
        $model        = model(NotificationModel::class);
        $notification = $model->where('user_id', $user->id)->find($id);

        if ($notification === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->markRead($id, $user->id);

        $base = rtrim(base_url(), '/');
        $url  = (string) $notification['url'];

        return redirect()->to(str_starts_with($url, $base . '/') || $url === $base ? $url : site_url('/'));
    }

    public function readAll(): ResponseInterface
    {
        model(NotificationModel::class)->markAllRead($this->currentUser()->id);

        if ($this->wantsJson()) {
            return $this->response->setJSON(['ok' => true, 'unread' => 0, 'csrf' => csrf_hash()]);
        }

        return redirect()->back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
