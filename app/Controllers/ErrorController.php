<?php

namespace App\Controllers;

/**
 * Halaman error kustom (dipakai sebagai override 404 di Config\Routing).
 */
class ErrorController extends BaseController
{
    public function notFound(?string $message = null): string
    {
        $this->response->setStatusCode(404);

        return view('errors/html/error_404', ['message' => $message]);
    }
}
