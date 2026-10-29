<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengaturan aplikasi: identitas perusahaan, logo, mata uang, tarif PPN (khusus admin).
 */
class SettingController extends BaseController
{
    private const KEYS = ['company_name', 'company_address', 'company_phone', 'currency', 'tax_rate'];

    public function edit(): string
    {
        $settings = model(SettingModel::class);

        return view('admin/settings', [
            'title'    => 'Pengaturan',
            'settings' => $settings->allValues(),
        ]);
    }

    public function update(): RedirectResponse
    {
        $rules = [
            'company_name'    => ['label' => 'Nama perusahaan', 'rules' => 'required|max_length[150]'],
            'company_address' => ['label' => 'Alamat perusahaan', 'rules' => 'permit_empty|max_length[500]'],
            'company_phone'   => ['label' => 'Telepon perusahaan', 'rules' => 'permit_empty|valid_phone'],
            'currency'        => ['label' => 'Mata uang', 'rules' => 'required|alpha|exact_length[3]'],
            'tax_rate'        => ['label' => 'Tarif PPN', 'rules' => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]'],
        ];

        $input             = $this->request->getPost(self::KEYS);
        $input['tax_rate'] = str_replace(',', '.', (string) ($input['tax_rate'] ?? ''));

        if (! $this->validateData($input, $rules)) {
            return redirect()->to(site_url('admin/settings'))->withInput()->with('errors', $this->validator->getErrors());
        }

        $logo = $this->request->getFile('logo');

        // Aturan file hanya dijalankan bila ada file yang diunggah.
        if ($logo !== null && $logo->getError() !== UPLOAD_ERR_NO_FILE && ! $this->validate([
            'logo' => ['label' => 'Logo', 'rules' => 'uploaded[logo]|is_image[logo]|mime_in[logo,image/png,image/jpeg,image/webp]|max_size[logo,1024]|max_dims[logo,1200,1200]'],
        ])) {
            return redirect()->to(site_url('admin/settings'))->withInput()->with('errors', $this->validator->getErrors());
        }

        $settings = model(SettingModel::class);
        $old      = $settings->allValues();

        foreach (self::KEYS as $key) {
            $value = trim((string) $input[$key]);
            $settings->setValue($key, match ($key) {
                'currency'      => strtoupper($value),
                'company_phone' => normalize_phone($value),
                default         => $value,
            });
        }

        if ($logo !== null && $logo->isValid() && ! $logo->hasMoved()) {
            $name = 'logo-' . date('YmdHis') . '.' . $logo->guessExtension();
            $logo->move(FCPATH . 'uploads', $name);
            $this->removeLogo($old['company_logo'] ?? null);
            $settings->setValue('company_logo', $name);
        } elseif ($this->request->getPost('remove_logo') === '1') {
            $this->removeLogo($old['company_logo'] ?? null);
            $settings->setValue('company_logo', '');
        }

        service('auditLogger')->log('update', 'settings', null, $old, $settings->allValues());

        return redirect()->to(site_url('admin/settings'))->with('success', 'Pengaturan berhasil disimpan.');
    }

    private function removeLogo(?string $file): void
    {
        $path = FCPATH . 'uploads/' . basename((string) $file);

        if ($file !== null && $file !== '' && is_file($path)) {
            unlink($path);
        }
    }
}
