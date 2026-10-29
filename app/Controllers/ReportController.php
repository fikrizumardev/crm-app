<?php

namespace App\Controllers;

use App\Libraries\Csv;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Laporan untuk admin & manager (rute dilindungi filter role:admin,manager).
 */
class ReportController extends BaseController
{
    public function index(): RedirectResponse
    {
        return redirect()->to(site_url('reports/performance'));
    }

    public function performance(): ResponseInterface|string
    {
        $period = $this->period();
        $data   = service('reports')->salesPerformance($this->currentUser(), $period['from'], $period['to']);

        if ($this->isExport()) {
            $rows = array_map(static fn (array $r): array => [
                $r['name'], $r['leads'], $r['deals'], $r['won_count'], (int) round($r['won_value']), $r['lost_count'], self::percent($r['win_rate']), $r['activities'],
            ], [...$data['rows'], ['name' => 'Total'] + $data['totals']]);

            return $this->csv('kinerja-sales', $period, ['Sales', 'Lead baru', 'Deal dibuat', 'Won (jumlah)', 'Won (Rp)', 'Lost', 'Win rate (%)', 'Aktivitas'], $rows);
        }

        return $this->page('performance', 'Kinerja Sales', $period, $data);
    }

    public function winLoss(): ResponseInterface|string
    {
        $period = $this->period();
        $data   = service('reports')->winLoss($this->currentUser(), $period['from'], $period['to']);

        if ($this->isExport()) {
            $rows   = array_map(static fn (array $r): array => [$r['name'], $r['count'], (int) round($r['value']), self::percent($r['percent'])], $data['reasons']);
            $rows[] = ['Total Lost', $data['lost_count'], (int) round($data['lost_value']), ''];
            $rows[] = ['Total Won', $data['won_count'], (int) round($data['won_value']), ''];

            return $this->csv('win-loss', $period, ['Alasan kalah', 'Jumlah deal', 'Nilai (Rp)', 'Porsi (%)'], $rows);
        }

        return $this->page('win-loss', 'Win/Loss', $period, $data);
    }

    public function sources(): ResponseInterface|string
    {
        $period = $this->period();
        $data   = service('reports')->leadSources($this->currentUser(), $period['from'], $period['to']);

        if ($this->isExport()) {
            $rows = array_map(static fn (array $r): array => [$r['name'], $r['leads'], $r['qualified'], $r['converted'], $r['unqualified'], self::percent($r['rate'])], $data);

            return $this->csv('sumber-lead', $period, ['Sumber', 'Lead masuk', 'Kualifikasi+', 'Dikonversi', 'Tidak qualified', 'Konversi (%)'], $rows);
        }

        return $this->page('sources', 'Sumber Lead', $period, ['rows' => $data]);
    }

    public function forecast(): ResponseInterface|string
    {
        $data = service('reports')->forecast($this->currentUser());

        if ($this->isExport()) {
            $rows   = array_map(static fn (array $r): array => [$r['label'], $r['count'], (int) round($r['amount']), (int) round($r['weighted'])], $data['rows']);
            $rows[] = ['Total', $data['totals']['count'], (int) round($data['totals']['amount']), (int) round($data['totals']['weighted'])];

            return $this->csv('forecast', null, ['Bulan perkiraan closing', 'Deal terbuka', 'Nilai (Rp)', 'Forecast tertimbang (Rp)'], $rows);
        }

        return $this->page('forecast', 'Forecast', null, $data);
    }

    /**
     * @return array{from: string, to: string, preset: string|null, label: string}
     */
    private function period(): array
    {
        return service('reports')->period(
            $this->request->getGet('preset'),
            $this->request->getGet('from'),
            $this->request->getGet('to'),
        );
    }

    private function isExport(): bool
    {
        return $this->request->getGet('export') === 'csv';
    }

    /**
     * @param array<string, mixed>|null $period
     * @param array<string, mixed>      $data
     */
    private function page(string $report, string $title, ?array $period, array $data): string
    {
        return view('reports/' . $report, [
            'title'  => 'Laporan ' . $title,
            'report' => $report,
            'period' => $period,
            'data'   => $data,
            'user'   => $this->currentUser(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $period
     * @param list<string>              $headers
     * @param list<list<mixed>>         $rows
     */
    private function csv(string $name, ?array $period, array $headers, array $rows): ResponseInterface
    {
        $suffix = $period === null ? date('Ymd') : str_replace('-', '', $period['from']) . '-' . str_replace('-', '', $period['to']);

        return Csv::download($this->response, "laporan-{$name}-{$suffix}.csv", Csv::build($headers, $rows));
    }

    private static function percent(?float $value): string
    {
        return $value === null ? '' : str_replace('.', ',', (string) $value);
    }
}
