<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Struk {{ $nomor }}</title>
<style>
  @page { size: 72mm auto; margin: 4mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; width: 64mm; }
  h1 { font-size: 13px; margin: 0; text-align: center; }
  .tengah { text-align: center; }
  .meta { margin: 6px 0; }
  table { width: 100%; border-collapse: collapse; }
  td { vertical-align: top; padding: 1px 0; }
  .kanan { text-align: right; }
  .garis { border-top: 1px dashed #111; margin: 6px 0; }
  .total { font-size: 12px; font-weight: bold; }
</style>
</head>
<body>
<h1>{{ $perusahaan }}</h1>
<p class="tengah">{{ $cabang }}</p>
<div class="garis"></div>
<div class="meta">
  <div>No. nota: {{ $nomor }}</div>
  <div>Tanggal: {{ $tanggal }}</div>
  <div>Kasir: {{ $kasir }}</div>
  @if($pelanggan)<div>Pelanggan: {{ $pelanggan }}</div>@endif
  @if($status !== 'lunas')<div>Status: {{ $status }}</div>@endif
</div>
<div class="garis"></div>
<table>
  @foreach($baris as $b)
  <tr>
    <td>{{ $b['nama'] }}<br><span>{{ $b['qty'] }} x {{ number_format((float) $b['harga'], 0, ',', '.') }}</span></td>
    <td class="kanan">{{ number_format((float) $b['total'], 0, ',', '.') }}</td>
  </tr>
  @endforeach
</table>
<div class="garis"></div>
<table>
  <tr><td>Subtotal</td><td class="kanan">{{ number_format((float) $subtotal, 0, ',', '.') }}</td></tr>
  @if((float) $diskon > 0)<tr><td>Diskon</td><td class="kanan">-{{ number_format((float) $diskon, 0, ',', '.') }}</td></tr>@endif
  @if((float) $pajak > 0)<tr><td>Pajak</td><td class="kanan">{{ number_format((float) $pajak, 0, ',', '.') }}</td></tr>@endif
  <tr><td class="total">Total</td><td class="kanan total">Rp{{ number_format((float) $total, 0, ',', '.') }}</td></tr>
</table>
<div class="garis"></div>
<table>
  @foreach($bayar as $p)
  <tr><td>{{ ucfirst($p['cara']) }}@if($p['diterima']) (diterima {{ number_format((float) $p['diterima'], 0, ',', '.') }})@endif</td><td class="kanan">{{ number_format((float) $p['nominal'], 0, ',', '.') }}</td></tr>
  @endforeach
</table>
<div class="garis"></div>
<p class="tengah">Terima kasih atas kunjungan Anda</p>
</body>
</html>
