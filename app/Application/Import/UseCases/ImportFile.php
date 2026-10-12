<?php

declare(strict_types=1);

namespace App\Application\Import\UseCases;

use App\Application\Import\DTOs\ImportResult;
use App\Application\Stock\UseCases\RecordStockMovement;
use App\Domain\Access\Repositories\AssignmentRepositoryInterface;
use App\Domain\Access\Repositories\RoleRepositoryInterface;
use App\Domain\Branch\Repositories\BranchRepositoryInterface;
use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemPriceRepositoryInterface;
use App\Domain\Catalog\Repositories\ItemRepositoryInterface;
use App\Domain\Payroll\Services\PtkpTable;
use App\Domain\Purchase\Repositories\SupplierRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ImportFile
{
    public function __construct(
        private ItemRepositoryInterface $items,
        private CategoryRepositoryInterface $categories,
        private ItemPriceRepositoryInterface $prices,
        private BranchRepositoryInterface $branches,
        private SupplierRepositoryInterface $suppliers,
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private AssignmentRepositoryInterface $assignments,
        private RecordStockMovement $recordMovement,
    ) {}

    /** @param list<array<string, string|null>> $rows */
    public function __invoke(Company $company, string $type, array $rows, bool $dryRun): ImportResult
    {
        $validated = match ($type) {
            'items' => $this->validateItems($company, $rows),
            'stock' => $this->validateStock($company, $rows),
            'suppliers' => $this->validateSuppliers($company, $rows),
            'employees' => $this->validateEmployees($company, $rows),
            default => abort(404),
        };

        if ($validated->failed() || $dryRun) {
            return $validated;
        }

        return DB::transaction(function () use ($company, $type, $rows) {
            $count = 0;
            foreach ($rows as $row) {
                match ($type) {
                    'items' => $this->applyItem($company, $row),
                    'stock' => $this->applyStock($company, $row),
                    'suppliers' => $this->applySupplier($company, $row),
                    'employees' => $this->applyEmployee($company, $row),
                    default => abort(404),
                };
                $count++;
            }

            return new ImportResult($count, []);
        });
    }

    /** @param list<array<string, string|null>> $rows */
    private function validateItems(Company $company, array $rows): ImportResult
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $rowErrors = [];
            $line = $index + 2;
            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                $rowErrors[] = 'code wajib diisi.';
            } elseif (isset($seen[$code])) {
                $rowErrors[] = "code {$code} duplikat di file.";
            } elseif ($this->items->findByCodeForCompany($company, $code) !== null) {
                $rowErrors[] = "code {$code} sudah ada.";
            }
            $seen[$code] = true;

            if (trim((string) ($row['name'] ?? '')) === '') {
                $rowErrors[] = 'name wajib diisi.';
            }

            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if (! in_array($type, ['barang', 'jasa'], true)) {
                $rowErrors[] = 'type harus barang atau jasa.';
            } elseif ($type === 'barang' && trim((string) ($row['unit'] ?? '')) === '') {
                $rowErrors[] = 'unit wajib untuk barang.';
            }

            $hasBranch = trim((string) ($row['branch_code'] ?? '')) !== '';
            $hasPrice = trim((string) ($row['price'] ?? '')) !== '';
            if ($hasBranch xor $hasPrice) {
                $rowErrors[] = 'branch_code dan price harus diisi bersamaan.';
            } elseif ($hasBranch && $this->branches->findByCodeForCompany($company, trim($row['branch_code'])) === null) {
                $rowErrors[] = 'branch_code tidak ada di perusahaan ini.';
            } elseif ($hasPrice && (! is_numeric($row['price']) || (float) $row['price'] < 0)) {
                $rowErrors[] = 'price harus angka >= 0.';
            }

            if ($rowErrors !== []) {
                $errors[] = ['row' => $line, 'errors' => $rowErrors];
            }
        }

        return new ImportResult(0, $errors);
    }

    /** @param array<string, string|null> $row */
    private function applyItem(Company $company, array $row): void
    {
        $categoryId = null;
        $categoryName = trim((string) ($row['category'] ?? ''));
        if ($categoryName !== '') {
            $category = $company->categories()->firstOrCreate(['name' => $categoryName]);
            $categoryId = $category->id;
        }

        $type = strtolower(trim((string) $row['type']));
        $item = $this->items->createForCompany($company, [
            'code' => trim((string) $row['code']),
            'name' => trim((string) $row['name']),
            'type' => $type,
            'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
            'category_id' => $categoryId,
            'track_stock' => $type === 'barang',
            'status' => 'aktif',
        ]);

        if (trim((string) ($row['branch_code'] ?? '')) !== '') {
            $branch = $this->branches->findByCodeForCompany($company, trim($row['branch_code']));
            $this->prices->upsert($item, $branch, (string) $row['price']);
        }
    }

    /** @param list<array<string, string|null>> $rows */
    private function validateStock(Company $company, array $rows): ImportResult
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowErrors = [];
            $line = $index + 2;

            if ($this->items->findByCodeForCompany($company, trim((string) ($row['item_code'] ?? ''))) === null) {
                $rowErrors[] = 'item_code tidak ada.';
            }

            if ($this->branches->findByCodeForCompany($company, trim((string) ($row['branch_code'] ?? ''))) === null) {
                $rowErrors[] = 'branch_code tidak ada.';
            }

            if (! is_numeric($row['qty'] ?? null) || (float) $row['qty'] <= 0) {
                $rowErrors[] = 'qty harus angka > 0.';
            }

            if (! is_numeric($row['unit_cost'] ?? null) || (float) $row['unit_cost'] < 0) {
                $rowErrors[] = 'unit_cost harus angka >= 0.';
            }

            if ($rowErrors !== []) {
                $errors[] = ['row' => $line, 'errors' => $rowErrors];
            }
        }

        return new ImportResult(0, $errors);
    }

    /** @param array<string, string|null> $row */
    private function applyStock(Company $company, array $row): void
    {
        $item = $this->items->findByCodeForCompany($company, trim($row['item_code']));
        $branch = $this->branches->findByCodeForCompany($company, trim($row['branch_code']));

        ($this->recordMovement)(
            $company, $branch, $item->id, 'pembelian',
            (float) $row['qty'], (float) $row['unit_cost'], 'Stok awal (impor)',
        );
    }

    /** @param list<array<string, string|null>> $rows */
    private function validateSuppliers(Company $company, array $rows): ImportResult
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $rowErrors = [];
            $line = $index + 2;
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                $rowErrors[] = 'name wajib diisi.';
            } elseif (isset($seen[$name])) {
                $rowErrors[] = "name {$name} duplikat di file.";
            } elseif ($company->suppliers()->where('name', $name)->exists()) {
                $rowErrors[] = "name {$name} sudah ada.";
            }
            $seen[$name] = true;

            if ($rowErrors !== []) {
                $errors[] = ['row' => $line, 'errors' => $rowErrors];
            }
        }

        return new ImportResult(0, $errors);
    }

    /** @param array<string, string|null> $row */
    private function applySupplier(Company $company, array $row): void
    {
        $this->suppliers->createForCompany($company, [
            'name' => trim((string) $row['name']),
            'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
            'address' => trim((string) ($row['address'] ?? '')) ?: null,
        ]);
    }

    /** @param list<array<string, string|null>> $rows */
    private function validateEmployees(Company $company, array $rows): ImportResult
    {
        $errors = [];
        $seenEmails = [];

        foreach ($rows as $index => $row) {
            $rowErrors = [];
            $line = $index + 2;
            $email = strtolower(trim((string) ($row['email'] ?? '')));

            if (trim((string) ($row['name'] ?? '')) === '') {
                $rowErrors[] = 'name wajib diisi.';
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'email tidak valid.';
            } elseif (isset($seenEmails[$email])) {
                $rowErrors[] = "email {$email} duplikat di file.";
            } elseif ($this->users->findByEmail($email) !== null) {
                $rowErrors[] = "email {$email} sudah terdaftar.";
            }
            $seenEmails[$email] = true;

            $branchCode = trim((string) ($row['branch_code'] ?? ''));
            if ($branchCode !== '' && $this->branches->findByCodeForCompany($company, $branchCode) === null) {
                $rowErrors[] = 'branch_code tidak ada.';
            }

            $roleName = trim((string) ($row['role'] ?? ''));
            if ($roleName !== '' && $this->roles->findByNameForCompany($company, $roleName) === null) {
                $rowErrors[] = "role {$roleName} tidak ada di perusahaan ini.";
            }

            $password = (string) ($row['password'] ?? '');
            if ($password !== '' && strlen($password) < 8) {
                $rowErrors[] = 'password minimal 8 karakter.';
            }

            $ptkp = strtoupper(trim((string) ($row['ptkp_status'] ?? '')));
            if ($ptkp !== '' && ! in_array($ptkp, PtkpTable::statuses(), true)) {
                $rowErrors[] = 'ptkp_status tidak dikenal.';
            }

            if ($rowErrors !== []) {
                $errors[] = ['row' => $line, 'errors' => $rowErrors];
            }
        }

        return new ImportResult(0, $errors);
    }

    /** @param array<string, string|null> $row */
    private function applyEmployee(Company $company, array $row): void
    {
        $password = trim((string) ($row['password'] ?? ''));

        $user = $this->users->create([
            'name' => trim((string) $row['name']),
            'email' => strtolower(trim((string) $row['email'])),
            'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
            'password' => Hash::make($password !== '' ? $password : Str::random(16)),
            'ptkp_status' => strtoupper(trim((string) ($row['ptkp_status'] ?? ''))) ?: 'TK/0',
        ]);

        $branchId = null;
        $branchCode = trim((string) ($row['branch_code'] ?? ''));
        if ($branchCode !== '') {
            $branchId = $this->branches->findByCodeForCompany($company, $branchCode)->id;
        }

        $roleId = null;
        $roleName = trim((string) ($row['role'] ?? ''));
        if ($roleName !== '') {
            $role = $this->roles->findByNameForCompany($company, $roleName);
            $roleId = $role->id;
            setPermissionsTeamId($company->id);
            $user->assignRole($role);
        }

        $this->assignments->create($company, [
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'role_id' => $roleId,
            'started_at' => now()->toDateString(),
        ]);
    }
}
