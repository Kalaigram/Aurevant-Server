<?php

declare(strict_types=1);

namespace App\Domain\Access\Services;

/** Katalog permission tetap. Scope (sendiri/cabang/perusahaan) diatur di penempatan, bukan di sini. */
final class PermissionCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'transaksi.buat', 'transaksi.diskon', 'transaksi.void',
            'jasa.proses',
            'retur.buat', 'retur.setujui', 'nota.lihat', 'shift.buka', 'shift.tutup',
            'stok.transfer', 'stok.opname', 'po.buat', 'po.setujui',
            'keuangan.lihat', 'biaya.input', 'biaya.setujui', 'periode.tutup', 'laporan.konsolidasi',
            'jadwal.lihat', 'shift.kelola', 'absensi.koreksi', 'absensi.approve',
            'gaji.lihat_sendiri', 'gaji.kelola', 'gaji.setujui',
            'katalog.kelola', 'harga.atur', 'cabang.kelola', 'karyawan.kelola',
            'audit.lihat', 'dashboard.konsolidasi',
            'pelanggan.lihat', 'pelanggan.kelola', 'pelanggan.ekspor',
            'impor.jalankan', 'pengaturan.kelola',
        ];
    }
}
