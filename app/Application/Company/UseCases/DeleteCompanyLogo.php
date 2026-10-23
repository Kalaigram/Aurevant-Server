<?php

declare(strict_types=1);

namespace App\Application\Company\UseCases;

use App\Application\Company\DTOs\CompanyDTO;
use App\Domain\Company\Repositories\CompanyRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;

final class DeleteCompanyLogo
{
    public function __construct(
        private CompanyRepositoryInterface $companies,
    ) {}

    public function __invoke(Company $company): CompanyDTO
    {
        $oldPath = $company->logo_path;
        $updated = $this->companies->update($company, ['logo_path' => null]);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return CompanyDTO::fromModel($updated);
    }
}
