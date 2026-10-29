<?php

use App\Models\LeadModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\AppTestCase;

/**
 * Pembatasan data per owner (PRD §3).
 *
 * @internal
 */
final class OwnedModelTest extends AppTestCase
{
    public function testCakupanDaftarPerPeran(): void
    {
        $andi  = $this->makeLead('andi.saputra');
        $siti  = $this->makeLead('siti.rahma');
        $putri = $this->makeLead('putri.ayu');
        $model = model(LeadModel::class);

        $ids = static fn (array $rows): array => array_map('intval', array_column($rows, 'id'));

        $this->assertSame([$andi], $ids($model->visibleTo($this->user('andi.saputra'))->findAll()));
        $this->assertEqualsCanonicalizing([$andi, $siti], $ids($model->visibleTo($this->user('dewi.lestari'))->findAll()));
        $this->assertSame([$putri], $ids($model->visibleTo($this->user('hendra.wijaya'))->findAll()));
        $this->assertEqualsCanonicalizing([$andi, $siti, $putri], $ids($model->visibleTo($this->user('admin'))->findAll()));
    }

    public function testSalesTidakBisaMembacaMilikSalesLain(): void
    {
        $siti = $this->makeLead('siti.rahma');

        $this->expectException(PageNotFoundException::class);

        model(LeadModel::class)->findVisibleOrFail($siti, $this->user('andi.saputra'));
    }

    public function testMatriksUbahDanHapus(): void
    {
        $model     = model(LeadModel::class);
        $own       = $model->find($this->makeLead('andi.saputra'));
        $converted = $model->find($this->makeLead('andi.saputra', ['status' => 'converted']));
        $other     = $model->find($this->makeLead('siti.rahma'));

        $andi = $this->user('andi.saputra');
        $this->assertTrue($model->canEdit($andi, $own));
        $this->assertTrue($model->canDelete($andi, $own));
        $this->assertFalse($model->canDelete($andi, $converted), 'Sales tidak boleh menghapus lead yang sudah final');
        $this->assertFalse($model->canEdit($andi, $other));
        $this->assertFalse($model->canDelete($andi, $other));

        $dewi = $this->user('dewi.lestari');
        $this->assertTrue($model->canDelete($dewi, $converted));
        $this->assertTrue($model->canEdit($dewi, $other));

        $hendra = $this->user('hendra.wijaya');
        $this->assertFalse($model->canEdit($hendra, $own), 'Manager hanya untuk timnya');

        $this->assertTrue($model->canDelete($this->user('admin'), $converted));
    }
}
