<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

/** CoA bawaan tiap perusahaan baru. */
final class DefaultChartOfAccounts
{
    /** @return list<array{code: string, name: string, type: string}> */
    public static function all(): array
    {
        return [
            ['code' => '1110', 'name' => 'Kas Tunai', 'type' => 'aset'],
            ['code' => '1120', 'name' => 'Bank / Transfer', 'type' => 'aset'],
            ['code' => '1130', 'name' => 'QRIS', 'type' => 'aset'],
            ['code' => '1140', 'name' => 'E-Wallet', 'type' => 'aset'],
            ['code' => '1210', 'name' => 'Piutang Usaha', 'type' => 'aset'],
            ['code' => '1310', 'name' => 'Persediaan Barang', 'type' => 'aset'],
            ['code' => '2110', 'name' => 'Hutang Supplier', 'type' => 'liabilitas'],
            ['code' => '2120', 'name' => 'Uang Muka Pelanggan', 'type' => 'liabilitas'],
            ['code' => '2130', 'name' => 'PPN Keluaran', 'type' => 'liabilitas'],
            ['code' => '2140', 'name' => 'Hutang Gaji', 'type' => 'liabilitas'],
            ['code' => '3100', 'name' => 'Modal Pemilik', 'type' => 'ekuitas'],
            ['code' => '4100', 'name' => 'Pendapatan Barang', 'type' => 'pendapatan'],
            ['code' => '4200', 'name' => 'Pendapatan Jasa', 'type' => 'pendapatan'],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'beban'],
            ['code' => '6100', 'name' => 'Beban Gaji', 'type' => 'beban'],
            ['code' => '6200', 'name' => 'Beban Operasional', 'type' => 'beban'],
        ];
    }

    /** @return array<string, string> metode bayar => kode akun */
    public static function paymentAccounts(): array
    {
        return [
            'tunai' => '1110',
            'transfer' => '1120',
            'qris' => '1130',
            'ewallet' => '1140',
            'dp' => '2120',
        ];
    }
}
