<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Services\MasterDataService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Master data: sumber lead, industri, alasan kalah, dan tahap pipeline (khusus admin).
 */
class MasterDataController extends BaseController
{
    public function index(): string
    {
        $service = service('masterData');
        $lists   = [];

        foreach (MasterDataService::TYPES as $type => $config) {
            $lists[$type] = ['label' => $config['label'], 'items' => $service->items($type)];
        }

        return view('admin/master', [
            'title'  => 'Master Data',
            'lists'  => $lists,
            'stages' => $service->stages(),
        ]);
    }

    public function create(string $type): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->save($this->type($type), null, (string) $this->request->getPost('name')), 'Data berhasil ditambahkan.', $type);
    }

    public function update(string $type, int $id): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->save($this->type($type), $id, (string) $this->request->getPost('name')), 'Data berhasil diubah.', $type);
    }

    public function delete(string $type, int $id): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->delete($this->type($type), $id), 'Data berhasil dihapus.', $type);
    }

    public function createStage(): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->saveStage(null, (string) $this->request->getPost('name'), $this->request->getPost('probability')), 'Tahap baru ditambahkan.', 'stages');
    }

    public function updateStage(int $id): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->saveStage($id, (string) $this->request->getPost('name'), $this->request->getPost('probability')), 'Tahap berhasil diubah.', 'stages');
    }

    public function deleteStage(int $id): RedirectResponse
    {
        return $this->run(fn () => service('masterData')->deleteStage($id), 'Tahap berhasil dihapus.', 'stages');
    }

    /**
     * Simpan urutan tahap dari drag-and-drop (JSON).
     */
    public function reorderStages(): ResponseInterface
    {
        try {
            service('masterData')->reorderStages((array) $this->request->getPost('order'));
        } catch (ValidationFailedException $e) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'message' => array_values($e->getErrors())[0], 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON(['ok' => true, 'message' => 'Urutan tahap disimpan.', 'csrf' => csrf_hash()]);
    }

    private function type(string $type): string
    {
        if (! isset(MasterDataService::TYPES[$type])) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $type;
    }

    private function run(callable $action, string $message, string $anchor): RedirectResponse
    {
        $back = site_url('admin/master') . '#' . $anchor;

        try {
            $action();
        } catch (ValidationFailedException $e) {
            return redirect()->to($back)->with('error', (string) array_values($e->getErrors())[0]);
        } catch (BusinessRuleException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        return redirect()->to($back)->with('success', $message);
    }
}
