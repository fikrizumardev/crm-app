<?php

namespace App\Controllers;

use App\Models\TaskModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $user   = $this->currentUser();
        $owners = $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user);
        $owner  = ctype_digit((string) $this->request->getGet('owner')) && isset($owners[(int) $this->request->getGet('owner')])
            ? (int) $this->request->getGet('owner')
            : null;

        $tasks   = model(TaskModel::class);
        $today   = $tasks->forTab('today', $user, $user->id)->findAll(5);
        $overdue = $tasks->forTab('overdue', $user, $user->id)->findAll(5);
        $counts  = $tasks->tabCounts($user, $user->id);

        return view('dashboard/index', [
            'title'        => 'Dashboard',
            'user'         => $user,
            'data'         => service('dashboard')->data($user, $owner),
            'owners'       => $owners,
            'ownerId'      => $owner,
            'todayTasks'   => $today,
            'overdueTasks' => $overdue,
            'taskCounts'   => $counts,
        ]);
    }
}
