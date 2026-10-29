<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Services\ReassignService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Alihkan owner banyak record sekaligus dari halaman daftar (rute role:admin,manager).
 */
class ReassignController extends BaseController
{
    public function reassign(string $path): RedirectResponse
    {
        if (! isset(ReassignService::ENTITIES[$path])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $back = (string) $this->request->getPost('return_to');
        $back = str_starts_with($back, rtrim(base_url(), '/') . '/') ? $back : site_url($path);

        try {
            $moved = service('reassign')->reassign($path, (array) $this->request->getPost('ids'), $this->request->getPost('owner_id'), $this->currentUser());
        } catch (BusinessRuleException|ValidationFailedException $e) {
            $message = $e instanceof ValidationFailedException ? (string) array_values($e->getErrors())[0] : $e->getMessage();

            return redirect()->to($back)->with('error', $message);
        }

        return redirect()->to($back)->with('success', sprintf('%d data berhasil dialihkan ke owner baru.', $moved));
    }
}
