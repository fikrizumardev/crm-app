<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login untuk semua rute selain /login.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('auth')->check()) {
            return null;
        }

        if ($request instanceof IncomingRequest && ($request->isAJAX() || str_contains($request->getHeaderLine('Accept'), 'application/json'))) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Sesi Anda telah berakhir. Silakan masuk kembali.']);
        }

        if ($request->getMethod() === 'GET') {
            session()->set('redirect_url', current_url(true)->__toString());
        }

        return redirect()->to(site_url('login'))->with('error', 'Silakan masuk terlebih dahulu.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
