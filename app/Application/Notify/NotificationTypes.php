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
            'sale.void',
            'sale.retur',
            'opname.disetujui',
            'shift.selisih',
            'langganan.jatuh_tempo',
        ];
    }
}
