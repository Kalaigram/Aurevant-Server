<?php

declare(strict_types=1);

namespace App\Application\Notify;

final class NotificationTypes
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'transfer.diajukan',
            'transfer.disetujui',
            'transfer.dikirim',
            'transfer.diterima',
            'po.diajukan',
            'po.disetujui',
            'po.diterima',
            'po.retur',
            'sale.void',
            'sale.retur',
            'opname.disetujui',
            'stok.menipis',
            'shift.selisih',
            'approval.diajukan',
            'cuti.diajukan',
            'cuti.disetujui',
            'cuti.ditolak',
            'lembur.diajukan',
            'lembur.disetujui',
            'lembur.ditolak',
            'tukar_shift.diajukan',
            'tukar_shift.disetujui',
            'tukar_shift.ditolak',
            'biaya.diajukan',
            'biaya.disetujui',
            'biaya.ditolak',
            'payroll.dibayar',
            'rekonsiliasi.selesai',
            'langganan.jatuh_tempo',
        ];
    }
}
