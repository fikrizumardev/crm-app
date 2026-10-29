<?php

namespace App\Controllers;

use App\Entities\User;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);
    }

    /**
     * User yang sedang login. Hanya dipanggil dari rute yang dilindungi filter `auth`.
     */
    protected function currentUser(): User
    {
        $user = service('auth')->user();

        if ($user === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $user;
    }

    /**
     * Respons 403 dengan halaman "akses ditolak".
     */
    protected function forbidden(string $message = 'Anda tidak memiliki akses untuk melakukan aksi ini.'): ResponseInterface
    {
        if ($this->wantsJson()) {
            return $this->response->setStatusCode(403)->setJSON(['message' => $message]);
        }

        return $this->response->setStatusCode(403)->setBody(view('errors/html/error_403', ['message' => $message]));
    }

    protected function wantsJson(): bool
    {
        return $this->request->isAJAX() || str_contains($this->request->getHeaderLine('Accept'), 'application/json');
    }
}
