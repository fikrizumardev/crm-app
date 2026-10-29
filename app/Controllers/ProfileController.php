<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class ProfileController extends BaseController
{
    public function edit(): string
    {
        return view('profile/edit', [
            'title' => 'Profil Saya',
            'user'  => $this->currentUser(),
        ]);
    }

    public function update(): RedirectResponse
    {
        $users = model(UserModel::class);
        $user  = $users->find($this->currentUser()->id);
        $old   = clone $user;

        $user->fill([
            'name'  => trim((string) $this->request->getPost('name')),
            'phone' => normalize_phone($this->request->getPost('phone')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
        ]);

        if (! $user->hasChanged()) {
            return redirect()->to(site_url('profile'))->with('info', 'Tidak ada perubahan data.');
        }

        if (! $users->save($user)) {
            return redirect()->back()->withInput()->with('errors', $users->errors());
        }

        service('auditLogger')->log('update', 'user', $user->id, $old, $user);
        service('auth')->refresh();

        return redirect()->to(site_url('profile'))->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(): string
    {
        return view('profile/password', [
            'title' => 'Ganti Password',
            'user'  => $this->currentUser(),
        ]);
    }

    public function updatePassword(): RedirectResponse
    {
        $user  = $this->currentUser();
        $rules = [
            'current_password' => ['label' => 'Password saat ini', 'rules' => 'required'],
            'new_password'     => ['label' => 'Password baru', 'rules' => 'required|max_length[255]|strong_password|differs[current_password]'],
            'confirm_password' => ['label' => 'Konfirmasi password', 'rules' => 'required|matches[new_password]'],
        ];
        $messages = [
            'new_password'     => ['differs' => 'Password baru harus berbeda dari password saat ini.'],
            'confirm_password' => ['matches' => 'Konfirmasi password tidak sama dengan password baru.'],
        ];

        $data = $this->request->getPost(['current_password', 'new_password', 'confirm_password']);

        if (! $this->validateData($data, $rules, $messages)) {
            return redirect()->to(site_url('profile/password'))->with('errors', $this->validator->getErrors());
        }

        if (! $user->verifyPassword((string) $data['current_password'])) {
            return redirect()->to(site_url('profile/password'))
                ->with('errors', ['current_password' => 'Password saat ini salah.']);
        }

        service('auth')->changePassword($user, (string) $data['new_password']);

        return redirect()->to(site_url('/'))->with('success', 'Password berhasil diganti.');
    }
}
