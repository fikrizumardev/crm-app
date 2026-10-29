<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\DealModel;
use App\Models\ProductModel;
use App\Models\QuotationModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\I18n\Time;
use Throwable;

/**
 * Penawaran harga (PRD §4 M8): perhitungan total di server sebagai sumber kebenaran
 * dan penomoran otomatis QUO/YYYY/MM/NNNN yang di-reset setiap bulan.
 */
class QuotationService
{
    public const MAX_ITEMS = 50;

    public function __construct(
        private readonly QuotationModel $quotations,
        private readonly ProductModel $products,
        private readonly AuditLogger $audit,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * Hitung total. Diskon per item dalam persen; PPN dari (subtotal − diskon).
     * Rumus yang sama dipakai di form (JavaScript) untuk tampilan.
     *
     * @param list<array{qty: float|int|string, unit_price: float|int|string, discount_pct: float|int|string}> $items
     *
     * @return array{lines: list<float>, subtotal: float, discount_total: float, tax_rate: float, tax_amount: float, grand_total: float}
     */
    public static function calculate(array $items, float $taxRate): array
    {
        $lines    = [];
        $subtotal = 0.0;
        $net      = 0.0;

        foreach ($items as $item) {
            $gross = round((float) $item['qty'] * (float) $item['unit_price'], 2);
            $line  = round($gross * (1 - (float) $item['discount_pct'] / 100), 2);

            $lines[] = $line;
            $subtotal += $gross;
            $net += $line;
        }

        $subtotal = round($subtotal, 2);
        $net      = round($net, 2);
        $tax      = round($net * $taxRate / 100, 2);

        return [
            'lines'          => $lines,
            'subtotal'       => $subtotal,
            'discount_total' => round($subtotal - $net, 2),
            'tax_rate'       => $taxRate,
            'tax_amount'     => $tax,
            'grand_total'    => round($net + $tax, 2),
        ];
    }

    /**
     * Nomor berikutnya untuk bulan tanggal penawaran. Harus dipanggil di dalam transaksi:
     * baris terakhir bulan itu dikunci (SELECT … FOR UPDATE) agar dua request tidak mendapat nomor sama.
     */
    public function nextNumber(string $issueDate): string
    {
        $prefix = 'QUO/' . substr($issueDate, 0, 4) . '/' . substr($issueDate, 5, 2) . '/';
        $last   = $this->db->query(
            'SELECT number FROM ' . $this->db->prefixTable('quotations') . ' WHERE number LIKE ? ORDER BY number DESC LIMIT 1 FOR UPDATE',
            [$prefix . '%'],
        )->getRow('number');

        $sequence = $last === null ? 1 : (int) substr((string) $last, strlen($prefix)) + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Buat penawaran untuk deal yang boleh diubah user.
     *
     * @param array<string, mixed> $deal
     * @param array<string, mixed> $input issue_date, valid_until, notes, items[]
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function create(array $deal, array $input, User $actor): int
    {
        if (! model(DealModel::class)->canEdit($actor, $deal)) {
            throw new BusinessRuleException('Anda tidak berhak membuat penawaran untuk deal ini.');
        }

        [$header, $items] = $this->prepare($input);
        $taxRate          = (float) str_replace(',', '.', (string) model(SettingModel::class)->getValue('tax_rate', '11'));
        $totals           = self::calculate($items, $taxRate);

        // Retry bila nomor bentrok dengan request lain (indeks unik sebagai pengaman terakhir).
        for ($attempt = 1; ; $attempt++) {
            $this->db->transBegin();

            try {
                $number = $this->nextNumber($header['issue_date']);
                $id     = $this->quotations->skipValidation()->insert($header + [
                    'number'         => $number,
                    'deal_id'        => $deal['id'],
                    'status'         => QuotationModel::STATUS_DRAFT,
                    'subtotal'       => $totals['subtotal'],
                    'discount_total' => $totals['discount_total'],
                    'tax_rate'       => $totals['tax_rate'],
                    'tax_amount'     => $totals['tax_amount'],
                    'grand_total'    => $totals['grand_total'],
                    'created_by'     => $actor->id,
                ]);
                $this->quotations->skipValidation(false);
                $this->insertItems((int) $id, $items, $totals['lines']);
                $this->audit->log('create', 'quotation', $id, null, ['number' => $number, 'deal_id' => $deal['id'], 'grand_total' => $totals['grand_total']]);
                $this->db->transCommit();

                return (int) $id;
            } catch (DatabaseException $e) {
                $this->db->transRollback();
                $this->quotations->skipValidation(false);

                if ($attempt >= 3 || ! str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->db->transRollback();
                $this->quotations->skipValidation(false);

                throw $e;
            }
        }
    }

    /**
     * Ubah penawaran (hanya status Draft). Item lama diganti seluruhnya.
     *
     * @param array<string, mixed> $quotation
     * @param array<string, mixed> $input
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function update(array $quotation, array $input, User $actor): void
    {
        $this->assertEditable($quotation, $actor);

        [$header, $items] = $this->prepare($input);
        $totals           = self::calculate($items, (float) $quotation['tax_rate']);
        $changes          = $header + [
            'subtotal'       => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_amount'     => $totals['tax_amount'],
            'grand_total'    => $totals['grand_total'],
        ];

        $this->db->transBegin();

        try {
            $this->quotations->skipValidation()->update($quotation['id'], $changes);
            $this->quotations->skipValidation(false);
            $this->db->table('quotation_items')->where('quotation_id', $quotation['id'])->delete();
            $this->insertItems((int) $quotation['id'], $items, $totals['lines']);
            $this->audit->log('update', 'quotation', $quotation['id'], $quotation, $changes);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $quotation
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function setStatus(array $quotation, string $status, User $actor): void
    {
        if (! isset(QuotationModel::STATUSES[$status])) {
            throw new ValidationFailedException(['status' => 'Status penawaran tidak valid.']);
        }

        if (! $this->quotations->canEdit($actor, $quotation)) {
            throw new BusinessRuleException('Anda tidak berhak mengubah penawaran ini.');
        }

        if ($status === $quotation['status']) {
            return;
        }

        $this->quotations->skipValidation()->update($quotation['id'], ['status' => $status]);
        $this->quotations->skipValidation(false);
        $this->audit->log('status_change', 'quotation', $quotation['id'], ['status' => $quotation['status']], ['status' => $status]);
    }

    /**
     * @param array<string, mixed> $quotation
     */
    public function delete(array $quotation, User $actor): void
    {
        if (! $this->quotations->canDelete($actor, $quotation)) {
            throw new BusinessRuleException('Penawaran yang sudah diterima hanya bisa dihapus manager atau admin.');
        }

        $this->quotations->delete($quotation['id']);
        $this->audit->log('delete', 'quotation', $quotation['id'], ['number' => $quotation['number']], null);
    }

    /**
     * @param array<string, mixed> $quotation
     *
     * @throws BusinessRuleException
     */
    public function assertEditable(array $quotation, User $actor): void
    {
        if (! $this->quotations->canEdit($actor, $quotation)) {
            throw new BusinessRuleException('Anda tidak berhak mengubah penawaran ini.');
        }

        if ($quotation['status'] !== QuotationModel::STATUS_DRAFT) {
            throw new BusinessRuleException('Hanya penawaran berstatus Draft yang bisa diubah. Kembalikan status ke Draft untuk merevisi.');
        }
    }

    /**
     * Validasi & normalisasi input form.
     *
     * @param array<string, mixed> $input
     *
     * @return array{0: array{issue_date: string, valid_until: string, notes: string|null}, 1: list<array<string, mixed>>}
     *
     * @throws ValidationFailedException
     */
    private function prepare(array $input): array
    {
        $errors     = [];
        $issueDate  = trim((string) ($input['issue_date'] ?? '')) ?: Time::today()->format('Y-m-d');
        $validUntil = trim((string) ($input['valid_until'] ?? '')) ?: Time::parse($issueDate)->addDays(14)->format('Y-m-d');
        $isDate     = static fn (string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));

        if (! $isDate($issueDate)) {
            $errors['issue_date'] = 'Tanggal penawaran tidak valid.';
        }

        if (! $isDate($validUntil)) {
            $errors['valid_until'] = 'Tanggal berlaku sampai tidak valid.';
        } elseif ($isDate($issueDate) && $validUntil < $issueDate) {
            $errors['valid_until'] = 'Masa berlaku tidak boleh sebelum tanggal penawaran.';
        }

        $notes = trim((string) ($input['notes'] ?? ''));

        if (mb_strlen($notes) > 5000) {
            $errors['notes'] = 'Catatan maksimal 5.000 karakter.';
        }

        $rawItems = array_values(array_filter(
            is_array($input['items'] ?? null) ? $input['items'] : [],
            static fn ($row): bool => is_array($row) && implode('', array_map(static fn ($v): string => trim((string) $v), $row)) !== '',
        ));

        if ($rawItems === []) {
            $errors['items'] = 'Tambahkan minimal satu item penawaran.';
        } elseif (count($rawItems) > self::MAX_ITEMS) {
            $errors['items'] = sprintf('Maksimal %d item per penawaran.', self::MAX_ITEMS);
        }

        $active = array_column($this->products->activeOptions(), null, 'id');
        $items  = [];

        foreach ($rawItems as $index => $row) {
            $line        = $index + 1;
            $productId   = ctype_digit((string) ($row['product_id'] ?? '')) ? (int) $row['product_id'] : null;
            $product     = $productId === null ? null : ($active[$productId] ?? null);
            $description = trim((string) ($row['description'] ?? '')) ?: ($product['name'] ?? '');
            $qty         = str_replace(',', '.', trim((string) ($row['qty'] ?? '')));
            $price       = parse_rupiah($row['unit_price'] ?? null) ?? ($product === null ? '' : (string) $product['price']);
            $discount    = str_replace(',', '.', trim((string) ($row['discount_pct'] ?? ''))) ?: '0';

            if ($productId !== null && $product === null) {
                $errors["items.{$index}"] = "Baris {$line}: produk tidak ditemukan atau sudah nonaktif.";

                continue;
            }

            if ($description === '' || mb_strlen($description) > 255) {
                $errors["items.{$index}"] = "Baris {$line}: deskripsi wajib diisi (maks. 255 karakter).";
            } elseif (! is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 99999999) {
                $errors["items.{$index}"] = "Baris {$line}: qty harus lebih dari 0.";
            } elseif (! is_numeric($price) || (float) $price < 0) {
                $errors["items.{$index}"] = "Baris {$line}: harga satuan tidak valid.";
            } elseif (! is_numeric($discount) || (float) $discount < 0 || (float) $discount > 100) {
                $errors["items.{$index}"] = "Baris {$line}: diskon harus 0–100%.";
            }

            $items[] = [
                'product_id'   => $productId,
                'description'  => $description,
                'qty'          => is_numeric($qty) ? round((float) $qty, 2) : 0,
                'unit_price'   => is_numeric($price) ? round((float) $price, 2) : 0,
                'discount_pct' => is_numeric($discount) ? round((float) $discount, 2) : 0,
            ];
        }

        if ($errors !== []) {
            throw new ValidationFailedException($errors);
        }

        return [['issue_date' => $issueDate, 'valid_until' => $validUntil, 'notes' => $notes === '' ? null : $notes], $items];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<float>                $lines
     */
    private function insertItems(int $quotationId, array $items, array $lines): void
    {
        $now  = Time::now()->toDateTimeString();
        $rows = [];

        foreach ($items as $i => $item) {
            $rows[] = $item + ['quotation_id' => $quotationId, 'line_total' => $lines[$i], 'created_at' => $now, 'updated_at' => $now];
        }

        $this->db->table('quotation_items')->insertBatch($rows);
    }
}
