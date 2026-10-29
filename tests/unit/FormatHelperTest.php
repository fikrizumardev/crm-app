<?php

use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FormatHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('format');
    }

    public function testRupiah(): void
    {
        $this->assertSame('Rp 485.000.000', rupiah(485000000));
        $this->assertSame('Rp 1.250.000', rupiah('1250000.00'));
        $this->assertSame('Rp 0', rupiah(null));
        $this->assertSame('-Rp 15.000', rupiah(-15000));
        $this->assertSame('2.500', rupiah(2500, false));
    }

    public function testTanggalId(): void
    {
        $this->assertSame('28 Okt 2026', tanggal_id('2026-10-28'));
        $this->assertSame('1 Jan 2026 08:05', tanggal_id('2026-01-01 08:05:00', true));
        $this->assertSame('-', tanggal_id(null));
        $this->assertSame('-', tanggal_id(''));
        $this->assertSame('-', tanggal_id('0000-00-00 00:00:00'));
    }

    public function testWaktuRelatif(): void
    {
        $now = Time::parse('2026-10-28 14:00:00', 'Asia/Jakarta');

        $this->assertSame('baru saja', waktu_relatif('2026-10-28 13:59:30', $now));
        $this->assertSame('5 menit lalu', waktu_relatif('2026-10-28 13:55:00', $now));
        $this->assertSame('3 jam lalu', waktu_relatif('2026-10-28 11:00:00', $now));
        $this->assertSame('kemarin', waktu_relatif('2026-10-27 09:00:00', $now));
        $this->assertSame('besok', waktu_relatif('2026-10-29 09:00:00', $now));
        $this->assertSame('4 hari lalu', waktu_relatif('2026-10-24 10:00:00', $now));
        $this->assertSame('dalam 3 hari', waktu_relatif('2026-10-31 10:00:00', $now));
        $this->assertSame('1 Jun 2026', waktu_relatif('2026-06-01 10:00:00', $now));
    }

    public function testNomorHp(): void
    {
        $this->assertSame('081234567810', normalize_phone('0812-3456-7810'));
        $this->assertSame('081234567810', normalize_phone('+62 812 3456 7810'));
        $this->assertSame('081234567810', normalize_phone('6281234567810'));
        $this->assertNull(normalize_phone('  '));

        $this->assertSame('0812-3456-7810', format_hp('081234567810'));
        $this->assertSame('(031) 3541288', format_hp('0313541288'));
        $this->assertSame('(0411) 852123', format_hp('0411852123'));
        $this->assertSame('485000000', parse_rupiah('Rp 485.000.000'));
        $this->assertSame('1250000.50', parse_rupiah('1.250.000,50'));
        $this->assertSame('1500.50', parse_rupiah('1500.50'));
        $this->assertNull(parse_rupiah(''));
        $this->assertSame('https://wa.me/6281234567810', wa_link('0812-3456-7810'));
        $this->assertNull(wa_link(null));
    }

    public function testNpwp(): void
    {
        $this->assertSame('01.234.567.8-901.234', format_npwp('012345678901234'));
        $this->assertSame('3171234567890123', format_npwp('3171234567890123'));
        $this->assertSame('-', format_npwp(null));
    }
}
