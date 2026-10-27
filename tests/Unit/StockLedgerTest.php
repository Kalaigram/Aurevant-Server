<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Stock\Exceptions\InsufficientStockException;
use App\Domain\Stock\Services\StockLedger;
use PHPUnit\Framework\TestCase;

class StockLedgerTest extends TestCase
{
    public function test_pembelian_menghitung_moving_average(): void
    {
        $ledger = new StockLedger;

        $pertama = $ledger->apply(0, 0, 'pembelian', 100, 50000);
        $this->assertEquals(100, $pertama->newBalance);
        $this->assertEquals(50000, $pertama->newAvgCost);

        $kedua = $ledger->apply(100, 50000, 'pembelian', 100, 70000);
        $this->assertEquals(200, $kedua->newBalance);
        $this->assertEqualsWithDelta(60000, $kedua->newAvgCost, 0.01);
    }

    public function test_penjualan_menghitung_hpp_tanpa_ubah_avg(): void
    {
        $ledger = new StockLedger;

        $hasil = $ledger->apply(200, 60000, 'penjualan', 50);

        $this->assertEquals(-50, $hasil->qtyDelta);
        $this->assertEquals(150, $hasil->newBalance);
        $this->assertEquals(60000, $hasil->newAvgCost);
        $this->assertEquals(3000000, $hasil->hppAmount);
    }

    public function test_stok_negatif_dilarang(): void
    {
        $ledger = new StockLedger;

        $this->expectException(InsufficientStockException::class);
        $ledger->apply(10, 50000, 'penjualan', 11);
    }

    public function test_penyesuaian_boleh_negatif_selama_cukup(): void
    {
        $ledger = new StockLedger;

        $kurang = $ledger->apply(100, 50000, 'penyesuaian', -5);
        $this->assertEquals(95, $kurang->newBalance);

        $this->expectException(InsufficientStockException::class);
        $ledger->apply(95, 50000, 'penyesuaian', -96);
    }

    public function test_transfer_keluar_seperti_penjualan(): void
    {
        $ledger = new StockLedger;

        $hasil = $ledger->apply(100, 50000, 'transfer_keluar', 40);

        $this->assertEquals(-40, $hasil->qtyDelta);
        $this->assertEquals(60, $hasil->newBalance);
        $this->assertEquals(2000000, $hasil->hppAmount);
    }

    public function test_transfer_masuk_membawa_harga_asal(): void
    {
        $ledger = new StockLedger;

        $hasil = $ledger->apply(10, 40000, 'transfer_masuk', 40, 50000);

        $this->assertEquals(50, $hasil->newBalance);
        $this->assertEqualsWithDelta(48000, $hasil->newAvgCost, 0.01);
    }

    public function test_opname_seperti_penyesuaian(): void
    {
        $ledger = new StockLedger;

        $hasil = $ledger->apply(100, 50000, 'opname', -3);

        $this->assertEquals(-3, $hasil->qtyDelta);
        $this->assertEquals(97, $hasil->newBalance);
    }

    public function test_retur_dan_void_menjaga_avg(): void
    {
        $ledger = new StockLedger;

        $retur = $ledger->apply(60, 60000, 'retur', 40);
        $this->assertEquals(100, $retur->newBalance);
        $this->assertEquals(60000, $retur->newAvgCost);

        $void = $ledger->apply(60, 60000, 'void', 40);
        $this->assertEquals(100, $void->newBalance);
        $this->assertEquals(60000, $void->newAvgCost);
    }

    public function test_retur_supplier_mengurangi_stok_dengan_hpp_rata_rata(): void
    {
        $ledger = new StockLedger;

        $hasil = $ledger->apply(10, 40000, 'retur_supplier', 3, 45000);

        $this->assertEquals(-3, $hasil->qtyDelta);
        $this->assertEquals(7, $hasil->newBalance);
        $this->assertEquals(40000, $hasil->newAvgCost);
        $this->assertEquals(120000, $hasil->hppAmount);
    }
}
