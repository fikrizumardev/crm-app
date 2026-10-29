<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Batasi rute berdasarkan peran, mis. `role:admin` atau `role:admin,manager`.
 * Dipasang setelah filter `auth`.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = service('auth')->user();

        if ($user !== null && $user->hasRole(...(array) $arguments)) {
            return null;
        }

        $response = service('response')->setStatusCode(403);

        if ($request instanceof IncomingRequest && ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json'))) {
            return $response->setJSON(['message' => 'Anda tidak memiliki akses ke halaman ini.']);
        }

        return $response->setBody(view('errors/html/error_403'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
