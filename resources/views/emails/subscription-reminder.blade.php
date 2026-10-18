@component('mail::message')
# {{ $expired ? 'Langganan berakhir' : 'Langganan segera berakhir' }}

Halo,

@if ($expired)
Masa langganan **{{ $companyName }}** (paket {{ $planName }}) sudah berakhir pada {{ $endDate }}.
Akses tulis sementara dibatasi sampai langganan diperpanjang.
@else
Paket **{{ $planName }}** untuk **{{ $companyName }}** akan berakhir pada {{ $endDate }}
({{ $daysLeft === 0 ? 'hari ini' : $daysLeft.' hari lagi' }}).
@endif

@component('mail::button', ['url' => rtrim(config('app.frontend_url'), '/').'/subscription'])
Perpanjang Langganan
@endcomponent

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
