<?php

declare(strict_types=1);

namespace App\Application\Company\UseCases;

use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreCompanyLogo
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {}

    public function __invoke(Company $company, UploadedFile $file): CompanyDTO
    {
        $path = $file->store('company-logos', 'public');

        if ($path === false) {
            abort(500, 'Logo gagal diunggah.');
        }

        $oldPath = $company->logo_path;
        $updated = $this->companies->update($company, ['logo_path' => $path]);

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return CompanyDTO::fromModel($updated);
    }
}
