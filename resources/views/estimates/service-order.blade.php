<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Estimasi {{ $nomor }}</title>
<style>
  @page { size: A4; margin: 16mm; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
  h1 { font-size: 20px; margin: 0; letter-spacing: .04em; }
  h2 { font-size: 13px; margin: 0 0 4px; }
  .kepala { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 10px; }
  .kanan { text-align: right; }
  .meta { display: flex; justify-content: space-between; gap: 24px; margin: 16px 0; }
  .meta div { line-height: 1.6; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #111; padding: 6px 4px; }
  td { padding: 6px 4px; border-bottom: 1px solid #ddd; vertical-align: top; }
  .ringkas { width: 45%; margin-left: auto; margin-top: 12px; }
  .ringkas td { border: 0; padding: 3px 4px; }
  .total { font-size: 14px; font-weight: bold; border-top: 1px solid #111; }
  .catatan { margin-top: 18px; padding: 8px 10px; background: #f4f4f4; }
  .kaki { margin-top: 28px; font-size: 9px; color: #555; line-height: 1.5; }
</style>
</head>
<body>
<div class="kepala">
  <div>
    <h1>{{ $perusahaan }}</h1>
    @if($alamat)<div>{{ $alamat }}</div>@endif
    @if($telepon)<div>Telp. {{ $telepon }}</div>@endif
    <div>Cabang {{ $cabang }}</div>
  </div>
  <div class="kanan">
    <h2>ESTIMASI BIAYA</h2>
    <div>No. {{ $nomor }}</div>
    <div>Tanggal: {{ $tanggal }}</div>
  </div>
</div>

<div class="meta">
  <div>
    <strong>Pelanggan</strong><br>
    {{ $pelanggan }}<br>
    @if($telepon_pelanggan){{ $telepon_pelanggan }}@endif
  </div>
  <div class="kanan">
    @if($jadwal)<div>Jadwal: {{ $jadwal }}</div>@endif
    @if($teknisi)<div>Teknisi: {{ $teknisi }}</div>@endif
    <div>Status order: {{ ucfirst($status) }}</div>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Jasa</th>
      <th class="kanan">Qty</th>
      <th class="kanan">Harga</th>
      <th class="kanan">Subtotal</th>
    </tr>
  </thead>
  <tbody>
    @foreach($baris as $b)
    <tr>
      <td>
        {{ $b['nama'] }}
        @if($b['kode'])<br><span style="color:#666">{{ $b['kode'] }}</span>@endif
      </td>
      <td class="kanan">{{ $b['qty'] }} {{ $b['satuan'] }}</td>
      <td class="kanan">{{ number_format((float) $b['harga'], 0, ',', '.') }}</td>
      <td class="kanan">{{ number_format((float) $b['total'], 0, ',', '.') }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<table class="ringkas">
  <tr><td class="total">Total estimasi</td><td class="kanan total">Rp{{ number_format((float) $total, 0, ',', '.') }}</td></tr>
  @if((float) $dp > 0)
  <tr><td>DP diterima</td><td class="kanan">Rp{{ number_format((float) $dp, 0, ',', '.') }}</td></tr>
  <tr><td>Sisa estimasi</td><td class="kanan">Rp{{ number_format((float) $sisa, 0, ',', '.') }}</td></tr>
  @endif
</table>

@if($catatan)
<div class="catatan"><strong>Catatan:</strong> {{ $catatan }}</div>
@endif

<div class="kaki">
  Estimasi ini bukan nota pembayaran. Harga dapat berubah bila ada perubahan lingkup pekerjaan atau bahan.
  Dokumen dibuat otomatis pada {{ now()->toDateTimeString() }}.
</div>
</body>
</html>
