<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Auth;

/**
 * User dengan `must_change_password = 1` hanya boleh membuka halaman ganti password (dan logout).
 */
class ForcePasswordChangeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = service('auth')->user();

        if ($user === null || ! $user->must_change_password) {
            return null;
        }

        $path = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';

        if (in_array($path, config(Auth::class)->passwordChangeAllowedPaths, true)) {
            return null;
        }

        return redirect()->to(site_url('profile/password'))
            ->with('warning', 'Demi keamanan, ganti password Anda terlebih dahulu sebelum melanjutkan.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
