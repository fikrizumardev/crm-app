<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class AuthController extends BaseController
{
    public function login(): RedirectResponse|string
    {
        if (service('auth')->check()) {
            return redirect()->to(site_url('/'));
        }

        return view('auth/login', ['username' => session('login_username') ?? '']);
    }

    public function attempt(): RedirectResponse
    {
        $rules = [
            'username' => ['label' => 'Username', 'rules' => 'required|max_length[100]'],
            'password' => ['label' => 'Password', 'rules' => 'required|max_length[255]'],
        ];

        $username = (string) $this->request->getPost('username');

        // Password sengaja tidak disimpan di flash session (tanpa withInput()).
        if (! $this->validateData($this->request->getPost(['username', 'password']), $rules)) {
            return redirect()->to(site_url('login'))
                ->with('errors', $this->validator->getErrors())
                ->with('login_username', $username);
        }

        $result = service('auth')->attempt(
            $username,
            (string) $this->request->getPost('password'),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to(site_url('login'))
                ->with('error', $result->message)
                ->with('login_username', $username);
        }

        $target = $this->safeRedirectUrl(session('redirect_url'));
        session()->remove('redirect_url');

        return redirect()->to($target)->with('success', 'Selamat datang, ' . $result->user->firstName() . '!');
    }

    public function logout(): RedirectResponse
    {
        service('auth')->logout();

        return redirect()->to(site_url('login'))->with('success', 'Anda telah keluar.');
    }

    /**
     * Hanya izinkan redirect ke URL internal aplikasi.
     */
    private function safeRedirectUrl(mixed $url): string
    {
        $base = rtrim(base_url(), '/');

        if (is_string($url) && ($url === $base || str_starts_with($url, $base . '/'))) {
            return $url;
        }

        return site_url('/');
    }
}
