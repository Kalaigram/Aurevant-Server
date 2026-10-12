<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

/** Template subrole bawaan. Pemilik boleh ubah isinya per perusahaan. */
final class RoleTemplates
{
    /** @return array<string, list<string>> */
    public static function all(): array
    {
        return [
            'Pemilik' => ['*'],
            'Manajer Cabang' => [
                'transaksi.buat', 'transaksi.diskon', 'transaksi.void',
                'retur.buat', 'retur.setujui', 'nota.lihat', 'shift.buka', 'shift.tutup',
                'stok.transfer', 'stok.opname', 'po.buat',
                'biaya.input', 'jadwal.lihat', 'jadwal.atur', 'absensi.koreksi', 'absensi.approve',
                'pelanggan.lihat', 'pelanggan.kelola', 'dashboard.konsolidasi',
            ],
            'Kasir' => ['transaksi.buat', 'nota.lihat', 'shift.buka', 'shift.tutup', 'pelanggan.lihat'],
            'Staf Gudang' => ['stok.transfer', 'stok.opname', 'po.buat', 'nota.lihat'],
            'Pelaksana Jasa' => ['nota.lihat', 'jadwal.lihat', 'gaji.lihat_sendiri'],
            'Admin Keuangan' => [
                'keuangan.lihat', 'biaya.input', 'biaya.setujui', 'periode.tutup', 'laporan.konsolidasi',
                'gaji.kelola', 'nota.lihat', 'dashboard.konsolidasi',
            ],
            'HR' => [
                'karyawan.kelola', 'jadwal.lihat', 'jadwal.atur', 'shift.kelola',
                'absensi.koreksi', 'absensi.approve', 'gaji.kelola', 'gaji.setujui',
            ],
        ];
    }

    /** @return list<string> */
    public static function permissionsFor(string $template, array $catalog): array
    {
        $wanted = self::all()[$template] ?? [];

        if (in_array('*', $wanted, true)) {
            return $catalog;
        }

        return array_values(array_intersect($wanted, $catalog));
    }
}
