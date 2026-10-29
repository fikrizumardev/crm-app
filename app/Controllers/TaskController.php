<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\ActivityModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use App\Services\RelatedRecord;
use CodeIgniter\HTTP\RedirectResponse;

class TaskController extends BaseController
{
    private TaskModel $tasks;

    public function __construct()
    {
        $this->tasks = model(TaskModel::class);
    }

    public function index(): string
    {
        $user     = $this->currentUser();
        $tab      = array_key_exists((string) $this->request->getGet('tab'), TaskModel::TABS) ? (string) $this->request->getGet('tab') : 'today';
        $assignee = ! $user->isSales() && ctype_digit((string) $this->request->getGet('assignee')) ? (int) $this->request->getGet('assignee') : null;
        $tasks    = $this->tasks->forTab($tab, $user, $assignee)->paginate(25);

        return view('tasks/index', [
            'title'      => 'Tugas',
            'tab'        => $tab,
            'tasks'      => $tasks,
            'pager'      => $this->tasks->pager,
            'counts'     => $this->tasks->tabCounts($user, $assignee),
            'related'    => ActivityModel::relatedLabels($tasks),
            'assignees'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'assigneeId' => $assignee,
            'user'       => $user,
        ]);
    }

    public function create(): RedirectResponse
    {
        $input = $this->request->getPost(['title', 'description', 'due_at', 'priority', 'assigned_to', 'related_type', 'related_id']);
        $back  = $this->backUrl($input['related_type'] ?? null, $input['related_id'] ?? null);

        try {
            service('taskService')->create($input, $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to($back)->withInput()->with('errors', $e->getErrors())->with('open_modal', 'task');
        }

        return redirect()->to($back)->with('success', 'Tugas berhasil dibuat.');
    }

    public function done(int $id): RedirectResponse
    {
        return $this->changeStatus($id, 'complete', 'Tugas ditandai selesai.');
    }

    public function reopen(int $id): RedirectResponse
    {
        return $this->changeStatus($id, 'reopen', 'Tugas dibuka kembali.');
    }

    public function delete(int $id): RedirectResponse
    {
        $user = $this->currentUser();
        $task = $this->tasks->findVisibleOrFail($id, $user);

        try {
            service('taskService')->delete($task, $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to($this->returnUrl())->with('error', $e->getMessage());
        }

        return redirect()->to($this->returnUrl())->with('success', 'Tugas dihapus.');
    }

    private function changeStatus(int $id, string $method, string $message): RedirectResponse
    {
        $user = $this->currentUser();
        $task = $this->tasks->findVisibleOrFail($id, $user);

        try {
            service('taskService')->{$method}($task, $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to($this->returnUrl())->with('error', $e->getMessage());
        }

        return redirect()->to($this->returnUrl())->with('success', $message);
    }

    private function backUrl(mixed $type, mixed $id): string
    {
        if (isset(ActivityModel::RELATED[(string) $type]) && ctype_digit((string) $id)) {
            return RelatedRecord::url((string) $type, (string) $id);
        }

        return site_url('tasks');
    }

    /**
     * Kembali ke halaman asal bila masih di dalam aplikasi, selain itu ke daftar tugas.
     */
    private function returnUrl(): string
    {
        $url  = (string) $this->request->getPost('return_to');
        $base = rtrim(base_url(), '/');

        return str_starts_with($url, $base . '/') ? $url : site_url('tasks');
    }
}
