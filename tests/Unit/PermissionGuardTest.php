<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Access\Services\PermissionCatalog;
use Tests\TestCase;

/** Penjaga nol-yatim: setiap izin katalog harus terpakai di backend/frontend, dan sebaliknya. */
class PermissionGuardTest extends TestCase
{
    /** @return list<string> isi semua file kode (tanpa katalog & template itu sendiri) */
    private function kode(): array
    {
        $lewati = ['PermissionCatalog.php', 'RoleTemplates.php'];
        $haystacks = [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            base_path('app'),
            \FilesystemIterator::SKIP_DOTS
        ));
        /** @var \SplFileInfo $file */
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getFilename(), $lewati, true)) {
                $haystacks[] = (string) file_get_contents($file->getPathname());
            }
        }
        $haystacks[] = (string) file_get_contents(base_path('routes/api.php'));
        $client = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            base_path('../client/src'),
            \FilesystemIterator::SKIP_DOTS
        ));
        /** @var \SplFileInfo $file */
        foreach ($client as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['vue', 'ts'], true)) {
                $haystacks[] = (string) file_get_contents($file->getPathname());
            }
        }

        return $haystacks;
    }

    public function test_setiap_izin_katalog_terpakai_di_kode(): void
    {
        $haystacks = $this->kode();
        $yatim = [];
        foreach (PermissionCatalog::all() as $izin) {
            $pakai = false;
            foreach ($haystacks as $isi) {
                if (str_contains($isi, $izin)) {
                    $pakai = true;
                    break;
                }
            }
            if (! $pakai) {
                $yatim[] = $izin;
            }
        }

        $this->assertSame([], $yatim, 'Izin yatim (tak terpakai): '.implode(', ', $yatim));
    }

    public function test_setiap_middleware_izin_terdaftar_di_katalog(): void
    {
        $routes = (string) file_get_contents(base_path('routes/api.php'));
        preg_match_all('/permission:([a-z0-9_.]+)/', $routes, $m);
        $asing = array_values(array_diff(array_unique($m[1]), PermissionCatalog::all()));

        $this->assertSame([], $asing, 'Middleware izin tak dikenal: '.implode(', ', $asing));
    }
}
