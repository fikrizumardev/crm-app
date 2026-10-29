<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Manajemen user (khusus admin).
 */
class UserController extends BaseController
{
    private const FIELDS = ['username', 'name', 'email', 'phone', 'role', 'manager_id', 'password'];

    private UserModel $users;

    public function __construct()
    {
        $this->users = model(UserModel::class);
    }

    public function index(): string
    {
        $filters = $this->request->getGet(['q', 'role', 'status']);

        $this->users->select('users.*, managers.name AS manager_name')
            ->join('users AS managers', 'managers.id = users.manager_id', 'left')
            ->orderBy('users.is_active', 'DESC')
            ->orderBy("FIELD(users.role, 'admin', 'manager', 'sales')", '', false)
            ->orderBy('users.name');

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->users->groupStart()->like('users.name', $q)->orLike('users.username', $q)->orLike('users.email', $q)->groupEnd();
        }

        if (isset(User::ROLES[$filters['role'] ?? ''])) {
            $this->users->where('users.role', $filters['role']);
        }

        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $this->users->where('users.is_active', $filters['status'] === 'active' ? 1 : 0);
        }

        return view('admin/users/index', [
            'title'     => 'Pengguna',
            'users'     => $this->users->paginate(25),
            'pager'     => $this->users->pager,
            'filters'   => $filters,
            'generated' => session()->getFlashdata('generated_password'),
            'me'        => $this->currentUser(),
        ]);
    }

    public function new(): string
    {
        return $this->form(new User(['role' => User::ROLE_SALES, 'is_active' => true]), 'Tambah Pengguna');
    }

    public function create(): RedirectResponse
    {
        try {
            $result = service('userService')->create($this->request->getPost(self::FIELDS), $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('admin/users/new'))->withInput()->with('errors', $e->getErrors());
        }

        $user = $this->users->find($result['id']);

        return redirect()->to(site_url('admin/users'))
            ->with('success', 'Pengguna ' . $user->name . ' berhasil dibuat.')
            ->with('generated_password', ['username' => $user->username, 'password' => $result['password'], 'reason' => 'Password awal']);
    }

    public function edit(int $id): string
    {
        return $this->form($this->findOrFail($id), 'Ubah Pengguna');
    }

    public function update(int $id): RedirectResponse
    {
        $user = $this->findOrFail($id);

        try {
            service('userService')->update($user, $this->request->getPost(self::FIELDS), $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("admin/users/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url("admin/users/{$id}/edit"))->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/users'))->with('success', 'Data pengguna ' . $user->name . ' diperbarui.');
    }

    public function activate(int $id): RedirectResponse
    {
        return $this->setActive($id, true);
    }

    public function deactivate(int $id): RedirectResponse
    {
        return $this->setActive($id, false);
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $user     = $this->findOrFail($id);
        $password = service('userService')->resetPassword($user, $this->currentUser());

        return redirect()->to(site_url('admin/users'))
            ->with('success', 'Password ' . $user->name . ' berhasil di-reset.')
            ->with('generated_password', ['username' => $user->username, 'password' => $password, 'reason' => 'Password baru']);
    }

    private function setActive(int $id, bool $active): RedirectResponse
    {
        $user = $this->findOrFail($id);

        try {
            service('userService')->setActive($user, $active, $this->currentUser());
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url('admin/users'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/users'))
            ->with('success', $user->name . ($active ? ' diaktifkan kembali.' : ' dinonaktifkan dan tidak bisa login lagi.'));
    }

    private function findOrFail(int $id): User
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }

    private function form(User $user, string $title): string
    {
        $managers = [];

        foreach ($this->users->where('role', User::ROLE_MANAGER)->where('is_active', 1)->orderBy('name')->findAll() as $manager) {
            $managers[$manager->id] = $manager->name;
        }

        return view('admin/users/form', [
            'title'    => $title,
            'user'     => $user,
            'managers' => $managers,
            'me'       => $this->currentUser(),
        ]);
    }
}
