<?php

namespace App\Libraries;

use App\Exceptions\BusinessRuleException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Baca/tulis CSV yang ramah Excel: UTF-8 dengan BOM, aman dari formula injection.
 */
class Csv
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * Susun isi file CSV.
     *
     * @param list<string>                                  $headers
     * @param iterable<list<bool|float|int|string|null>> $rows
     */
    public static function build(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, self::BOM);
        fputcsv($handle, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::sanitize(...), $row), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Respons unduhan file CSV.
     */
    public static function download(ResponseInterface $response, string $filename, string $csv): ResponseInterface
    {
        return $response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($csv);
    }

    /**
     * Cegah sel dieksekusi sebagai formula saat dibuka di spreadsheet.
     */
    public static function sanitize(bool|float|int|string|null $value): string
    {
        $value = (string) ($value ?? '');

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Baca file CSV menjadi baris asosiatif berdasarkan header (huruf kecil, spasi → `_`).
     * Delimiter `,` atau `;` dideteksi otomatis dari baris header.
     *
     * @return list<array{line: int, data: array<string, string>}>
     *
     * @throws BusinessRuleException
     */
    public static function read(string $path, int $maxRows): array
    {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            throw new BusinessRuleException('File CSV kosong.');
        }

        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'w+b');
        fwrite($handle, $content);
        rewind($handle);

        $headers = null;
        $rows    = [];
        $line    = 0;

        while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;

            if ($values === [null] || implode('', array_map('trim', $values)) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(static fn ($h): string => str_replace(' ', '_', strtolower(trim((string) $h))), $values);

                continue;
            }

            if (count($rows) >= $maxRows) {
                fclose($handle);

                throw new BusinessRuleException(sprintf('Maksimal %d baris data per impor. Pecah file menjadi beberapa bagian.', $maxRows));
            }

            $data = [];

            foreach ($headers as $i => $header) {
                $data[$header] = trim((string) ($values[$i] ?? ''));
            }

            $rows[] = ['line' => $line, 'data' => $data];
        }

        fclose($handle);

        if ($headers === null) {
            throw new BusinessRuleException('File CSV tidak memiliki header.');
        }

        return $rows;
    }

    /**
     * Header yang ada di file (dibaca ulang ringan untuk validasi kolom wajib).
     *
     * @return list<string>
     */
    public static function headers(string $path): array
    {
        $content = (string) file_get_contents($path, false, null, 0, 8192);

        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, 3);
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return array_map(
            static fn ($h): string => str_replace(' ', '_', strtolower(trim((string) $h))),
            str_getcsv($firstLine, $delimiter, '"', ''),
        );
    }
}
