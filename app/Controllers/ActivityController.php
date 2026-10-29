<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\ActivityModel;
use App\Services\RelatedRecord;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class ActivityController extends BaseController
{
    /**
     * Catat aktivitas dari halaman detail mana pun (form modal).
     */
    public function create(): RedirectResponse
    {
        $input = $this->request->getPost(['type', 'subject', 'description', 'related_type', 'related_id', 'activity_at']);
        $back  = isset(ActivityModel::RELATED[$input['related_type'] ?? '']) && ctype_digit((string) $input['related_id'])
            ? RelatedRecord::url($input['related_type'], $input['related_id'])
            : site_url('/');

        try {
            service('activityService')->create($input, $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to($back)->withInput()->with('errors', $e->getErrors())->with('open_modal', $this->request->getPost('_modal') === 'note' ? 'note' : 'activity');
        }

        return redirect()->to($back)->with('success', 'Aktivitas berhasil dicatat.');
    }

    public function delete(int $id): RedirectResponse
    {
        $user     = $this->currentUser();
        $activity = model(ActivityModel::class)->find($id);

        if ($activity === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        // Pastikan record tertaut masih dalam cakupan user.
        try {
            RelatedRecord::findVisible($activity['related_type'], $activity['related_id'], $user);
        } catch (ValidationFailedException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $back = RelatedRecord::url($activity['related_type'], $activity['related_id']);

        try {
            service('activityService')->delete($activity, $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        return redirect()->to($back)->with('success', 'Aktivitas dihapus.');
    }
}
