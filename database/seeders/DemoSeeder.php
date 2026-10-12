<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Access\Services\PermissionCatalog;
use App\Domain\Access\Services\RoleTemplates;
use App\Domain\Ledger\Services\DefaultChartOfAccounts;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppNotification;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DailyBranchSummary;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\Holiday;
use App\Models\Item;
use App\Models\ItemConsumable;
use App\Models\ItemPrice;
use App\Models\JournalEntry;
use App\Models\LeaveRequest;
use App\Models\NotificationPreference;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SalaryComponent;
use App\Models\Sale;
use App\Models\SaleSequence;
use App\Models\ScheduleOverride;
use App\Models\SchedulePattern;
use App\Models\ServiceOrder;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillPayment;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDeviceToken;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/** Data demo beragam (5–10 baris per tabel utama) untuk klik-klik UI. Login: owner@demo.id / password123 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $companies = $this->companies();
        $c1 = $companies[0];
        $branches = $this->branches($companies);
        $users = $this->users();
        $this->roles($c1, $users);
        $categories = $this->categories($c1);
        $items = $this->items($c1, $categories);
        $this->prices($branches, $items);
        $suppliers = $this->suppliers($c1);
        $customers = $this->customers($c1);

        $this->stock($c1, $branches, $items);
        $this->transfers($c1, $branches, $items);
        $this->opnames($c1, $branches, $items);
        $this->purchasing($c1, $branches, $suppliers, $items);
        $this->sales($c1, $branches, $users, $items, $customers);
        $this->services($c1, $branches, $users, $items, $customers);
        $this->hr($c1, $branches, $users);
        $this->payroll($c1, $users);
        $this->ledger($c1, $branches);
        $this->finance($c1, $branches, $customers);
        $this->billing($companies);
        $this->misc($c1, $branches, $users);
    }

    /** @return list<Company> */
    private function companies(): array
    {
        return [
            Company::firstOrCreate(['slug' => 'bmj'], ['name' => 'Bengkel Maju Jaya', 'status' => 'aktif']),
            Company::firstOrCreate(['slug' => 'tb'], ['name' => 'Toko Berkah', 'status' => 'aktif']),
        ];
    }

    /** @return list<Branch> */
    private function branches(array $companies): array
    {
        [$c1, $c2] = $companies;
        $rows = [
            [$c1->id, 'PST', 'Pusat Ciledug', 'pusat', 'Asia/Jakarta'],
            [$c1->id, 'CKR', 'Cabang Cikarang', 'cabang', 'Asia/Jakarta'],
            [$c1->id, 'MKS', 'Cabang Makassar', 'cabang', 'Asia/Makassar'],
            [$c1->id, 'GDG', 'Gudang Bitung', 'gudang', 'Asia/Jakarta'],
            [$c2->id, 'TBO', 'Toko Bontang', 'pusat', 'Asia/Makassar'],
            [$c2->id, 'TJP', 'Toko Jayapura', 'cabang', 'Asia/Jayapura'],
        ];

        $out = [];
        foreach ($rows as [$companyId, $code, $name, $type, $tz]) {
            $out[] = Branch::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'timezone' => $tz, 'status' => 'aktif']
            );
        }

        return $out;
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $rows = [
            'owner' => ['Pemilik Utama', 'owner@demo.id', '0811000001', 'TK/0'],
            'manajer' => ['Manajer Cikarang', 'manajer@demo.id', '0811000002', 'K/0'],
            'kasir1' => ['Kasir Sinta', 'kasir@demo.id', '0811000003', 'TK/0'],
            'kasir2' => ['Kasir Budi', 'kasir2@demo.id', '0811000004', 'TK/1'],
            'gudang' => ['Staf Gudang Agus', 'gudang@demo.id', '0811000005', 'K/1'],
            'teknisi' => ['Teknisi Rian', 'teknisi@demo.id', '0811000006', 'TK/0'],
            'hr' => ['HR Dewi', 'hr@demo.id', '0811000007', 'K/2'],
            'keuangan' => ['Keuangan Ratna', 'keuangan@demo.id', '0811000008', 'K/0'],
        ];

        $out = [];
        $i = 1;
        foreach ($rows as $key => [$name, $email, $phone, $ptkp]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone, 'password' => Hash::make('password123'), 'ptkp_status' => $ptkp]
            );
            User::query()->whereKey($user->id)->update(['employee_uuid' => sprintf('EMP-%04d', $i++)]);
            $out[$key] = $user->fresh();
        }

        return $out;
    }

    /** @param array<string, User> $users */
    private function roles(Company $company, array $users): void
    {
        setPermissionsTeamId($company->id);

        foreach (\App\Domain\Access\Services\PermissionCatalog::all() as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $map = [
            'Pemilik' => $users['owner'],
            'Manajer Cabang' => $users['manajer'],
            'Kasir' => $users['kasir1'],
            'Staf Gudang' => $users['gudang'],
            'Pelaksana Jasa' => $users['teknisi'],
            'HR' => $users['hr'],
            'Admin Keuangan' => $users['keuangan'],
        ];

        foreach ($map as $name => $user) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id],
                ['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id]
            );
            $role->syncPermissions(RoleTemplates::permissionsFor(
                $name, PermissionCatalog::all()
            ));
            $user->assignRole($role);

            EmployeeAssignment::firstOrCreate(
                ['user_id' => $user->id, 'company_id' => $company->id, 'ended_at' => null],
                ['branch_id' => null, 'role_id' => $role->id, 'started_at' => now()->subMonths(3)->toDateString()]
            );
        }
    }

    /** @return list<Category> */
    private function categories(Company $company): array
    {
        $out = [];
        foreach (['Oli & Pelumas', 'Sparepart', 'Ban', 'Jasa Servis', 'Aksesoris', 'Minuman'] as $name) {
            $out[] = Category::firstOrCreate(['company_id' => $company->id, 'name' => $name]);
        }

        return $out;
    }

    /** @return array<string, Item> */
    private function items(Company $company, array $categories): array
    {
        $byName = [];
        foreach ($categories as $category) {
            $byName[$category->name] = $category->id;
        }

        $rows = [
            'oli' => ['OLI-01', 'Oli MPX 1L', 'barang', 'botol', $byName['Oli & Pelumas'], true, null, null],
            'oli2' => ['OLI-02', 'Oli Federal 0.8L', 'barang', 'botol', $byName['Oli & Pelumas'], true, null, null],
            'kampas' => ['SP-01', 'Kampas Rem Depan', 'barang', 'pcs', $byName['Sparepart'], true, null, null],
            'aki' => ['SP-02', 'Aki GS 12V', 'barang', 'pcs', $byName['Sparepart'], true, null, null],
            'ban' => ['BN-01', 'Ban Tubeless 90/90', 'barang', 'pcs', $byName['Ban'], true, null, null],
            'helm' => ['AK-01', 'Helm Half Face', 'barang', 'pcs', $byName['Aksesoris'], true, null, null],
            'servis' => ['JS-01', 'Servis Ringan', 'jasa', null, $byName['Jasa Servis'], false, 'persen', 10],
            'tuneup' => ['JS-02', 'Tune Up + Injeksi', 'jasa', null, $byName['Jasa Servis'], false, 'nominal', 15000],
            'tambal' => ['JS-03', 'Tambal Ban', 'jasa', null, $byName['Jasa Servis'], false, 'persen', 20],
            'teh' => ['MM-01', 'Teh Botol', 'barang', 'botol', $byName['Minuman'], false, null, null],
        ];

        $out = [];
        foreach ($rows as $key => [$code, $name, $type, $unit, $categoryId, $track, $commType, $commValue]) {
            $out[$key] = Item::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name, 'type' => $type, 'unit' => $unit, 'category_id' => $categoryId,
                    'track_stock' => $track, 'commission_type' => $commType, 'commission_value' => $commValue ?? 0,
                    'status' => 'aktif',
                ]
            );
        }

        ItemConsumable::firstOrCreate(
            ['item_id' => $out['servis']->id, 'material_item_id' => $out['oli']->id], ['qty' => 1]
        );
        ItemConsumable::firstOrCreate(
            ['item_id' => $out['tuneup']->id, 'material_item_id' => $out['oli2']->id], ['qty' => 1]
        );

        return $out;
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function prices(array $branches, array $items): void
    {
        $base = [
            'oli' => 55000, 'oli2' => 48000, 'kampas' => 85000, 'aki' => 320000, 'ban' => 275000,
            'helm' => 185000, 'servis' => 75000, 'tuneup' => 150000, 'tambal' => 25000, 'teh' => 5000,
        ];

        foreach (array_slice($branches, 0, 4) as $i => $branch) {
            foreach ($base as $key => $price) {
                ItemPrice::firstOrCreate(
                    ['item_id' => $items[$key]->id, 'branch_id' => $branch->id],
                    ['price' => $price + ($i * 2000)]
                );
            }
        }
    }

    /** @return list<Supplier> */
    private function suppliers(Company $company): array
    {
        $out = [];
        foreach ([
            ['PT Sumber Makmur', '02155667788', 'Jl. Industri No. 8, Tangerang'],
            ['CV Barokah Jaya', '081233344455', 'Jl. Raya Cikarang KM 3'],
            ['PT Ban Nusantara', '02199887766', null],
            ['UD Lancar Abadi', null, 'Pasar Induk Cibitung Blok F'],
            ['PT Aki Prima', '03144556677', 'Jl. Rungkut Industri IV'],
            ['Toko Sinar Oli', '085611122233', null],
        ] as [$name, $phone, $address]) {
            $out[] = Supplier::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['phone' => $phone, 'address' => $address]
            );
        }

        return $out;
    }

    /** @return list<Customer> */
    private function customers(Company $company): array
    {
        $out = [];
        foreach ([
            ['Pak Dudi', '0812000001', 'Ciledug'],
            ['Bu Sari', '0812000002', 'Cikarang'],
            ['Koh Acong', '0812000003', null],
            ['Mas Eko', null, 'Bitung'],
            ['PT Angkasa Trans', '02177788899', 'Jl. Bandara No. 1'],
            ['Bengkel Rejeki (reseller)', '0812000006', null],
            ['Ibu Wulan', '0812000007', 'Makassar'],
            ['Pak Haji Mahmud', '0812000008', 'Ciledug'],
        ] as [$name, $phone, $address]) {
            $data = ['name' => $name, 'address' => $address];
            if ($phone !== null) {
                $data['phone'] = $phone;
            }
            $out[] = Customer::firstOrCreate(['company_id' => $company->id, 'name' => $name], $data);
        }

        return $out;
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function stock(Company $company, array $branches, array $items): void
    {
        $branch = $branches[0];
        $qtys = ['oli' => 40, 'oli2' => 35, 'kampas' => 20, 'aki' => 12, 'ban' => 15, 'helm' => 8, 'teh' => 24];

        foreach ($qtys as $key => $qty) {
            StockMovement::firstOrCreate(
                ['company_id' => $company->id, 'branch_id' => $branch->id, 'item_id' => $items[$key]->id, 'type' => 'pembelian', 'qty' => $qty],
                ['unit_cost' => 30000, 'hpp_amount' => $qty * 30000, 'balance_after' => $qty, 'note' => 'Stok awal demo']
            );
            StockBalance::firstOrCreate(
                ['branch_id' => $branch->id, 'item_id' => $items[$key]->id],
                ['company_id' => $company->id, 'qty' => $qty, 'avg_cost' => 30000]
            );
        }
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function transfers(Company $company, array $branches, array $items): void
    {
        $statuses = ['draft', 'diajukan', 'disetujui', 'dikirim', 'diterima', 'dibatalkan'];

        foreach ($statuses as $i => $status) {
            $transfer = StockTransfer::firstOrCreate(
                ['company_id' => $company->id, 'from_branch_id' => $branches[0]->id, 'to_branch_id' => $branches[1]->id, 'status' => $status, 'note' => 'Demo '.$status],
                ['shipped_at' => in_array($status, ['dikirim', 'diterima'], true) ? now() : null,
                    'received_at' => $status === 'diterima' ? now() : null]
            );
            $transfer->items()->firstOrCreate(
                ['item_id' => $items['oli']->id],
                ['qty_requested' => 5 + $i, 'qty_shipped' => 5 + $i, 'qty_received' => 5 + $i, 'cost_per_unit' => 30000]
            );
        }
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function opnames(Company $company, array $branches, array $items): void
    {
        foreach (['draft', 'diajukan', 'disetujui', 'ditolak', 'diajukan'] as $i => $status) {
            $opname = StockOpname::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'status' => $status, 'note' => 'Opname demo '.($i + 1),
            ]);
            $opname->items()->create(['item_id' => $items['oli']->id, 'qty_system' => 40, 'qty_counted' => 40 - $i]);
            $opname->items()->create(['item_id' => $items['kampas']->id, 'qty_system' => 20, 'qty_counted' => 20]);
        }
    }

    /** @param list<Branch> $branches @param array<string, Item> $items @param list<Supplier> $suppliers */
    private function purchasing(Company $company, array $branches, array $suppliers, array $items): void
    {
        $statuses = ['draft', 'diajukan', 'disetujui', 'dipesan', 'selesai', 'dibatalkan'];

        foreach ($statuses as $i => $status) {
            $order = PurchaseOrder::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'supplier_id' => $suppliers[$i]->id, 'status' => $status, 'note' => 'PO demo '.($i + 1),
            ]);
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id, 'item_id' => $items['oli']->id,
                'qty_ordered' => 10 + $i * 5, 'qty_received' => in_array($status, ['selesai'], true) ? 10 + $i * 5 : 0,
                'unit_price' => 30000,
            ]);

            if (in_array($status, ['dipesan', 'selesai'], true)) {
                $receipt = GoodsReceipt::create([
                    'company_id' => $company->id, 'purchase_order_id' => $order->id,
                    'due_at' => now()->addDays(14)->toDateString(), 'note' => 'Terima demo',
                ]);
                $receipt->items()->create([
                    'item_id' => $items['oli']->id, 'qty_received' => 10 + $i * 5, 'unit_cost' => 30000,
                ]);
                SupplierBill::firstOrCreate(
                    ['purchase_order_id' => $order->id],
                    ['company_id' => $company->id, 'amount' => (10 + $i * 5) * 30000,
                        'paid_amount' => $status === 'selesai' ? (10 + $i * 5) * 30000 : 100000,
                        'due_at' => now()->addDays(14)->toDateString()]
                );
            }
        }

        $bill = SupplierBill::query()->where('company_id', $company->id)->first();
        if ($bill !== null) {
            foreach ([50000, 50000] as $amount) {
                SupplierBillPayment::create([
                    'supplier_bill_id' => $bill->id, 'amount' => $amount,
                    'method' => 'transfer', 'paid_at' => now()->subDays(2)->toDateString(), 'note' => 'Cicilan demo',
                ]);
            }
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users @param array<string, Item> $items @param list<Customer> $customers */
    private function sales(Company $company, array $branches, array $users, array $items, array $customers): void
    {
        $branch = $branches[0];
        $shift = CashShift::firstOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $users['kasir1']->id, 'status' => 'terbuka'],
            ['opening_cash' => 200000, 'opened_at' => now()]
        );
        CashShift::firstOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches[1]->id, 'user_id' => $users['kasir2']->id, 'status' => 'ditutup'],
            ['opening_cash' => 150000, 'closing_cash_counted' => 480000, 'closing_cash_system' => 475000,
                'difference' => 5000, 'opened_at' => now()->subDay(), 'closed_at' => now()->subDay()]
        );

        $combos = [
            ['lunas', [['oli', 2, 0, 0], ['teh', 3, 0, 0]], [['tunai', 125000, 150000, null]]],
            ['lunas', [['servis', 1, 10, 0]], [['tunai', 67500, 70000, null], ['qris', 0, 0, null]]],
            ['lunas', [['kampas', 1, 0, 5000], ['oli2', 1, 0, 0]], [['transfer', 128000, 0, 'REF-001']]],
            ['lunas', [['ban', 2, 5, 0]], [['tunai', 261250, 0, null], ['ewallet', 261250, 0, null]]],
            ['lunas', [['helm', 1, 0, 0], ['teh', 2, 0, 0]], [['tunai', 195000, 200000, null]]],
            ['void', [['aki', 1, 0, 0]], [['tunai', 320000, 320000, null]]],
            ['retur', [['kampas', 2, 0, 0]], [['tunai', 170000, 170000, null]]],
            ['lunas', [['tuneup', 1, 0, 0]], [['tunai', 150000, 150000, null]]],
        ];

        $n = 1;
        foreach ($combos as [$status, $lines, $payments]) {
            $subtotal = 0;
            $discount = 0;
            foreach ($lines as [$key, $qty, $discPct, $discAmt]) {
                $price = ItemPrice::query()
                    ->where('item_id', $items[$key]->id)->where('branch_id', $branch->id)->value('price') ?? 50000;
                $gross = $price * $qty;
                $subtotal += $gross;
                $discount += $discPct > 0 ? $gross * $discPct / 100 : $discAmt;
            }
            $grand = $subtotal - $discount;

            $sale = Sale::firstOrCreate(
                ['branch_id' => $branch->id, 'number' => sprintf('%s-%s-%04d', $branch->code, now()->format('ymd'), $n)],
                ['company_id' => $company->id, 'shift_id' => $shift->id, 'user_id' => $users['kasir1']->id,
                    'status' => $status, 'subtotal' => $subtotal, 'discount_total' => $discount,
                    'tax_total' => 0, 'grand_total' => $grand,
                    'idempotency_key' => 'demo-sale-'.$n,
                    'customer_id' => $customers[$n % count($customers)]->id]
            );

            foreach ($lines as [$key, $qty, $discPct, $discAmt]) {
                $price = ItemPrice::query()
                    ->where('item_id', $items[$key]->id)->where('branch_id', $branch->id)->value('price') ?? 50000;
                $sale->items()->firstOrCreate(
                    ['sale_id' => $sale->id, 'item_id' => $items[$key]->id],
                    ['qty' => $qty, 'price' => $price, 'discount_percent' => $discPct,
                        'discount_amount' => $discAmt, 'tax_percent' => 0,
                        'line_total' => $price * $qty - ($discPct > 0 ? $price * $qty * $discPct / 100 : $discAmt)]
                );
            }
            foreach ($payments as [$method, $amount, $tendered, $ref]) {
                if ($amount > 0) {
                    $sale->payments()->firstOrCreate(
                        ['sale_id' => $sale->id, 'method' => $method, 'amount' => $amount],
                        ['tendered' => $tendered > 0 ? $tendered : null, 'reference' => $ref]
                    );
                }
            }
            $n++;
        }

        SaleSequence::firstOrCreate(
            ['branch_id' => $branch->id, 'date' => now()->toDateString()], ['last_number' => $n - 1]
        );

        $voidSale = Sale::query()->where('company_id', $company->id)->where('status', 'void')->first();
        if ($voidSale !== null) {
            $retur = $voidSale->returns()->firstOrCreate(
                ['kind' => 'void'],
                ['company_id' => $company->id, 'reason' => 'Salah input demo',
                    'refund_amount' => $voidSale->grand_total, 'status' => 'disetujui']
            );
            $firstItem = $voidSale->items()->first();
            if ($firstItem !== null) {
                $retur->items()->firstOrCreate(
                    ['sale_item_id' => $firstItem->id],
                    ['item_id' => $firstItem->item_id, 'qty' => 1, 'condition' => 'layak',
                        'refund_amount' => $firstItem->line_total]
                );
            }
        }

        $returSale = Sale::query()->where('company_id', $company->id)->where('status', 'retur')->first();
        if ($returSale !== null) {
            $retur = $returSale->returns()->firstOrCreate(
                ['kind' => 'retur'],
                ['company_id' => $company->id, 'reason' => 'Barang cacat demo', 'refund_method' => 'tunai',
                    'refund_amount' => 85000, 'status' => 'disetujui']
            );
            $firstItem = $returSale->items()->first();
            if ($firstItem !== null) {
                $retur->items()->firstOrCreate(
                    ['sale_item_id' => $firstItem->id],
                    ['item_id' => $firstItem->item_id, 'qty' => 1, 'condition' => 'rusak', 'refund_amount' => 85000]
                );
            }
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users @param array<string, Item> $items @param list<Customer> $customers */
    private function services(Company $company, array $branches, array $users, array $items, array $customers): void
    {
        $statuses = ['baru', 'diproses', 'selesai', 'dibayar', 'dibatalkan', 'baru', 'diproses'];

        foreach ($statuses as $i => $status) {
            $order = ServiceOrder::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'customer_id' => $customers[$i]->id,
                'assignee_id' => in_array($status, ['diproses', 'selesai', 'dibayar'], true) ? $users['teknisi']->id : null,
                'status' => $status, 'deposit_amount' => $i % 2 === 0 ? 20000 : 0,
                'note' => 'Order demo '.($i + 1),
            ]);
            $order->items()->create([
                'item_id' => $items['servis']->id, 'qty' => 1, 'price' => 75000,
                'commission_amount' => 7500,
            ]);
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users */
    private function hr(Company $company, array $branches, array $users): void
    {
        $shifts = [
            ['Pagi', '08:00', '16:00', 15],
            ['Siang', '13:00', '21:00', 15],
            ['Full Day', '08:00', '21:00', 30],
            ['Libur Shift', '00:00', '00:00', 0],
        ];
        foreach ($shifts as [$name, $start, $end, $tol]) {
            WorkShift::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['starts_at' => $start, 'ends_at' => $end, 'late_tolerance_minutes' => $tol]
            );
        }
        $pagi = WorkShift::query()->where('company_id', $company->id)->where('name', 'Pagi')->first();

        foreach (range(0, 6) as $weekday) {
            SchedulePattern::firstOrCreate(
                ['user_id' => $users['kasir1']->id, 'weekday' => $weekday],
                ['company_id' => $company->id, 'work_shift_id' => $weekday === 0 ? null : $pagi->id]
            );
        }

        foreach ([1, 5, 9] as $daysAgo) {
            ScheduleOverride::firstOrCreate(
                ['user_id' => $users['teknisi']->id, 'date' => now()->subDays($daysAgo)->toDateString()],
                ['company_id' => $company->id, 'work_shift_id' => $pagi->id, 'note' => 'Lembur demo']
            );
        }

        foreach ([
            [null, '2026-12-25', 'Natal'],
            [null, '2026-06-01', 'Pancasila'],
            [$company->id, now()->subDays(3)->toDateString(), 'Ulang tahun perusahaan'],
        ] as [$companyId, $date, $name]) {
            Holiday::firstOrCreate(['company_id' => $companyId, 'date' => $date], ['name' => $name]);
        }

        $statuses = ['hadir', 'terlambat', 'hadir', 'alpa', 'izin', 'sakit', 'cuti', 'hadir'];
        $i = 0;
        foreach (['kasir1', 'kasir2', 'gudang', 'teknisi'] as $key) {
            foreach ([1, 2] as $daysAgo) {
                Attendance::firstOrCreate(
                    ['user_id' => $users[$key]->id, 'branch_id' => $branches[0]->id,
                        'date' => now()->subDays($daysAgo)->toDateString()],
                    ['company_id' => $company->id, 'status' => $statuses[$i % count($statuses)],
                        'clock_in_at' => now()->subDays($daysAgo)->setTime(8, 5),
                        'late_minutes' => $statuses[$i % count($statuses)] === 'terlambat' ? 20 : 0,
                        'clock_in_device' => 'demo-device-'.$key]
                );
                $i++;
            }
        }

        foreach (['kasir1', 'gudang', 'teknisi', 'kasir2', 'hr'] as $key) {
            UserDevice::firstOrCreate(['user_id' => $users[$key]->id], ['device_id' => 'demo-device-'.$key]);
            UserDeviceToken::firstOrCreate(['user_id' => $users[$key]->id, 'token' => 'demo-fcm-'.$key], ['platform' => 'android']);
            NotificationPreference::firstOrCreate(
                ['user_id' => $users[$key]->id, 'type' => 'po.disetujui'], ['enabled' => true]
            );
        }

        $types = ['izin', 'sakit', 'cuti', 'izin', 'cuti', 'sakit'];
        $states = ['diajukan', 'disetujui', 'ditolak', 'diajukan', 'disetujui', 'diajukan'];
        foreach ($types as $i => $type) {
            LeaveRequest::create([
                'company_id' => $company->id, 'user_id' => $users['kasir1']->id, 'type' => $type,
                'from_date' => now()->addDays($i + 2)->toDateString(),
                'to_date' => now()->addDays($i + 3)->toDateString(),
                'reason' => 'Demo '.$type.' '.($i + 1), 'status' => $states[$i],
                'decided_by' => $states[$i] === 'diajukan' ? null : $users['hr']->id,
            ]);
        }
    }

    /** @param array<string, User> $users */
    private function payroll(Company $company, array $users): void
    {
        $components = [
            ['Gaji pokok', 'monthly_fixed', true, true, true, 5000000],
            ['Tunjangan tetap', 'monthly_fixed', true, true, false, 1000000],
            ['Uang makan', 'daily_rate', false, false, false, 25000],
            ['Komisi jasa', 'commission', false, true, false, 0],
            ['Lembur', 'manual', false, true, false, 0],
            ['Tunjangan transport', 'monthly_fixed', false, false, false, 500000],
            ['Potongan kasbon', 'manual', false, false, false, 0],
        ];

        $compModels = [];
        foreach ($components as [$name, $type, $deductible, $taxable, $bpjs, $amount]) {
            $comp = SalaryComponent::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['calc_type' => $type, 'deductible' => $deductible, 'taxable' => $taxable,
                    'bpjs_base' => $bpjs, 'active' => true]
            );
            $compModels[$name] = [$comp, $amount];
        }

        foreach (['kasir1', 'teknisi', 'gudang'] as $key) {
            foreach (['Gaji pokok', 'Tunjangan tetap', 'Uang makan'] as $name) {
                [$comp, $amount] = $compModels[$name];
                EmployeeSalary::firstOrCreate(
                    ['user_id' => $users[$key]->id, 'salary_component_id' => $comp->id],
                    ['company_id' => $company->id, 'amount' => $amount]
                );
            }
        }

        $periods = [
            ['Gaji Juni 2026', '2026-06-01', '2026-06-30', 'dibayar'],
            ['Gaji September 2026', '2026-09-01', '2026-09-30', 'disetujui'],
            ['Gaji Oktober 2026', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), 'draft'],
            ['Gaji Agustus 2026', '2026-08-01', '2026-08-31', 'dibayar'],
            ['THR 2026', '2026-03-01', '2026-03-31', 'review'],
        ];

        foreach ($periods as [$name, $from, $to, $status]) {
            $period = PayrollPeriod::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['date_from' => $from, 'date_to' => $to, 'divisor' => 25, 'status' => $status]
            );
            PayrollLine::firstOrCreate(
                ['payroll_period_id' => $period->id, 'user_id' => $users['kasir1']->id, 'label' => 'Gaji pokok'],
                ['salary_component_id' => $compModels['Gaji pokok'][0]->id, 'amount' => 5000000, 'is_manual' => false]
            );
            PayrollLine::firstOrCreate(
                ['payroll_period_id' => $period->id, 'user_id' => $users['kasir1']->id, 'label' => 'BPJS karyawan (1% Kes + 2% JHT + 1% JP)'],
                ['salary_component_id' => null, 'amount' => -200000, 'is_manual' => false]
            );
        }
    }

    /** @param list<Branch> $branches */
    private function ledger(Company $company, array $branches): void
    {
        $accounts = [];
        foreach (DefaultChartOfAccounts::all() as $row) {
            $accounts[$row['code']] = Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type']]
            );
        }

        $entries = [
            ['sale', 9001, 'Nota TKO-261012-0001', ['1110' => [110000, 0], '4100' => [0, 100000], '2130' => [0, 10000]]],
            ['expense', 9002, 'Biaya: Listrik', ['6200' => [250000, 0], '1110' => [0, 250000]]],
            ['po_receipt', 9003, 'Penerimaan PO #1', ['1310' => [300000, 0], '2110' => [0, 300000]]],
            ['bill_payment', 9004, 'Bayar hutang PO #1', ['2110' => [100000, 0], '1120' => [0, 100000]]],
            ['receivable', 9005, 'Piutang #1', ['1210' => [200000, 0], '4100' => [0, 200000]]],
            ['receivable_payment', 9006, 'Terima piutang #1', ['1110' => [70000, 0], '1210' => [0, 70000]]],
            ['payroll_accrue', 9007, 'Beban gaji Juni', ['6100' => [14500000, 0], '2140' => [0, 14500000]]],
            ['payroll_pay', 9008, 'Bayar gaji Juni', ['2140' => [14500000, 0], '1110' => [0, 14500000]]],
        ];

        foreach ($entries as [$sourceType, $sourceId, $memo, $lines]) {
            $entry = JournalEntry::firstOrCreate(
                ['source_type' => $sourceType, 'source_id' => $sourceId],
                ['company_id' => $company->id, 'branch_id' => $branches[0]->id,
                    'date' => now()->subDays(5)->toDateString(), 'memo' => $memo]
            );
            foreach ($lines as $code => [$debit, $credit]) {
                $entry->lines()->firstOrCreate(
                    ['account_id' => $accounts[$code]->id],
                    ['debit' => $debit, 'credit' => $credit]
                );
            }
        }
    }

    /** @param list<Branch> $branches @param list<Customer> $customers */
    private function finance(Company $company, array $branches, array $customers): void
    {
        $branch = $branches[0];
        $cats = [];
        foreach (['Listrik & Air', 'Gaji & THR', 'ATK', 'Sewa Ruko', 'Transport', 'Iklan'] as $name) {
            $cats[] = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => $name]);
        }

        $statuses = ['draft', 'disetujui', 'ditolak', 'disetujui', 'draft', 'disetujui', 'disetujui', 'ditolak'];
        $methods = ['tunai', 'transfer', 'tunai', 'transfer', 'tunai', 'tunai', 'transfer', 'tunai'];
        foreach ($statuses as $i => $status) {
            Expense::create([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'expense_category_id' => $cats[$i % count($cats)]->id,
                'amount' => 50000 + $i * 75000, 'method' => $methods[$i],
                'date' => now()->subDays($i)->toDateString(),
                'description' => 'Biaya demo '.($i + 1), 'status' => $status,
            ]);
        }

        for ($i = 0; $i < 6; $i++) {
            $receivable = Receivable::create([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'customer_id' => $i % 2 === 0 ? $customers[$i]->id : null,
                'amount' => 200000 + $i * 50000, 'paid_amount' => $i < 2 ? 50000 : 0,
                'due_at' => now()->addDays(7 - $i * 5)->toDateString(), 'note' => 'Bon demo '.($i + 1),
            ]);
            if ($i < 3) {
                ReceivablePayment::create([
                    'receivable_id' => $receivable->id, 'amount' => 50000,
                    'method' => ['tunai', 'transfer', 'qris'][$i % 3],
                    'paid_at' => now()->subDays(1)->toDateString(), 'note' => 'Cicilan demo',
                ]);
            }
        }
    }

    /** @param list<Company> $companies */
    private function billing(array $companies): void
    {
        $dasar = Plan::firstOrCreate(
            ['name' => 'Dasar'],
            ['max_branches' => 3, 'max_employees' => 20, 'features' => ['kasir', 'stok', 'po'], 'price' => 99000]
        );
        $pro = Plan::firstOrCreate(
            ['name' => 'Pro'],
            ['max_branches' => 100, 'max_employees' => 1000, 'features' => ['kasir', 'stok', 'po', 'laporan'], 'price' => 299000]
        );

        Subscription::firstOrCreate(
            ['company_id' => $companies[0]->id],
            ['plan_id' => $pro->id, 'status' => 'aktif', 'ends_at' => now()->addDays(20)->toDateString()]
        );
        Subscription::firstOrCreate(
            ['company_id' => $companies[1]->id],
            ['plan_id' => $dasar->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays(9)->toDateString()]
        );

        $states = [
            ['menunggu', null, 99000], ['lunas', -30, 99000], ['lunas', -60, 99000],
            ['menunggu', null, 299000], ['lunas', -30, 299000], ['lunas', -90, 99000],
        ];
        foreach ($states as $i => [$status, $paidAgo, $amount]) {
            SubscriptionInvoice::create([
                'company_id' => $companies[$i % 2]->id, 'plan_id' => ($i % 2 === 0 ? $dasar : $pro)->id,
                'amount' => $amount, 'status' => $status,
                'due_at' => now()->addDays($status === 'menunggu' ? 7 : -20)->toDateString(),
                'paid_at' => $paidAgo === null ? null : now()->addDays($paidAgo)->toDateString(),
                'note' => 'Demo '.($i + 1),
            ]);
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users */
    private function misc(Company $company, array $branches, array $users): void
    {
        foreach ([
            [$users['kasir1']->id, 'sale.void', 'sale', 6, ['status' => 'lunas'], ['status' => 'void']],
            [$users['owner']->id, 'po.approve', 'purchase_order', 2, ['status' => 'diajukan'], ['status' => 'disetujui']],
            [$users['manajer']->id, 'price.set', 'item', 3, ['price' => 50000], ['price' => 55000]],
            [$users['keuangan']->id, 'biaya.setujui', 'expense', 1, ['status' => 'draft'], ['status' => 'disetujui']],
            [$users['hr']->id, 'payroll.pay', 'payroll_period', 1, ['status' => 'disetujui'], ['status' => 'dibayar']],
            [null, 'attendance.mark-absent', 'attendance', 9, null, ['status' => 'alpa']],
            [$users['gudang']->id, 'stock.transfer.approve', 'stock_transfer', 3, ['status' => 'diajukan'], ['status' => 'disetujui']],
            [$users['owner']->id, 'approval.setujui', 'approval', 1, ['status' => 'diajukan'], ['status' => 'disetujui']],
        ] as [$userId, $action, $entityType, $entityId, $before, $after]) {
            AuditLog::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id, 'user_id' => $userId,
                'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId,
                'before' => $before, 'after' => $after, 'ip' => '127.0.0.1',
            ]);
        }

        foreach ([
            ['po.disetujui', 'PO #2 disetujui', null],
            ['sale.void', 'Nota dibatalkan', null],
            ['transfer.diajukan', 'Transfer #3 diajukan', null],
            ['approval.diajukan', 'Void nota menunggu persetujuan', '/approvals/1'],
            ['shift.selisih', 'Selisih shift Rp5.000', null],
            ['payroll.pay', 'Gaji Juni dibayar', null],
            ['sale.retur', 'Retur Rp85.000', null],
            ['opname.disetujui', 'Opname #3 disetujui', null],
        ] as [$type, $title, $link]) {
            AppNotification::create([
                'company_id' => $company->id, 'user_id' => $users['owner']->id,
                'type' => $type, 'title' => $title, 'body' => 'Notifikasi demo', 'link' => $link,
                'read_at' => str_starts_with($title, 'PO') ? now() : null,
            ]);
        }

        foreach ([
            ['2026-10', 'tutup'], ['2026-09', 'tutup'], ['2026-11', 'buka'],
            ['2026-08', 'tutup'], ['2026-12', 'buka'],
        ] as [$label, $status]) {
            [$year, $month] = explode('-', $label);
            AccountingPeriod::firstOrCreate(
                ['company_id' => $company->id, 'year' => (int) $year, 'month' => (int) $month],
                ['status' => $status, 'closed_at' => $status === 'tutup' ? now() : null]
            );
        }

        $actions = [
            ['sale.void', 'sale', 6, 'diajukan', ['reason' => 'Salah input demo']],
            ['sale.discount', null, null, 'diajukan', ['branch_id' => $branches[0]->id]],
            ['sale.refund', 'sale', 7, 'disetujui', ['reason' => 'Cacat']],
            ['po.approve', 'purchase_order', 2, 'disetujui', []],
            ['transfer.approve', 'stock_transfer', 2, 'diajukan', []],
            ['opname.approve', 'stock_opname', 2, 'ditolak', []],
        ];
        foreach ($actions as [$action, $entityType, $entityId, $status, $payload]) {
            Approval::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'entity_type' => $entityType, 'entity_id' => $entityId, 'action' => $action,
                'status' => $status, 'payload' => $payload === [] ? null : $payload,
                'requested_by' => $users['kasir1']->id,
                'decided_by' => $status === 'diajukan' ? null : $users['owner']->id,
                'decided_at' => $status === 'diajukan' ? null : now(),
                'note' => 'Demo',
            ]);
        }

        foreach (range(0, 7) as $daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            DailyBranchSummary::firstOrCreate(
                ['company_id' => $company->id, 'branch_id' => $branches[$daysAgo % 2]->id, 'date' => $date],
                ['omzet' => 500000 + $daysAgo * 100000, 'transactions' => 8 + $daysAgo,
                    'hpp_total' => 200000, 'discount_total' => 15000, 'tax_total' => 10000,
                    'cash_tunai' => 300000, 'refund_total' => $daysAgo === 3 ? 85000 : 0,
                    'net_omzet' => 490000 + $daysAgo * 100000]
            );
        }

        foreach (['2026-10-12', '2026-10-11'] as $date) {
            SaleSequence::firstOrCreate(
                ['branch_id' => $branches[1]->id, 'date' => $date], ['last_number' => 4]
            );
        }
    }
}
