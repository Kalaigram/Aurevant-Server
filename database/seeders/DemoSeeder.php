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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Data demo realistis: tiap halaman UI ada isinya dan beragam.
 * Login: pemilik@demo.id / manajer@demo.id / kasir@demo.id / teknisi@demo.id /
 *        gudang@demo.id / hr@demo.id / keuangan@demo.id (sandi: password123)
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        [$bengkel, $toko] = $this->companies();
        $branches = $this->branches($bengkel, $toko);
        $users = $this->users();
        $this->roles($bengkel, $users);
        $categories = $this->categories($bengkel);
        $items = $this->items($bengkel, $categories);
        $this->prices($branches, $items);
        $suppliers = $this->suppliers($bengkel);
        $customers = $this->customers($bengkel);

        $this->stock($bengkel, $branches, $items);
        $this->transfers($bengkel, $branches, $items);
        $this->opnames($bengkel, $branches, $items);
        $this->purchasing($bengkel, $branches, $suppliers, $items);
        $this->sales($bengkel, $branches, $users, $items, $customers);
        $this->services($bengkel, $branches, $users, $items, $customers);
        $this->hr($bengkel, $branches, $users);
        $this->payroll($bengkel, $users);
        $this->ledger($bengkel, $branches);
        $this->finance($bengkel, $branches, $customers);
        $this->billing($bengkel, $toko);
        $this->misc($bengkel, $branches, $users);
    }

    /** @return list<Company> */
    private function companies(): array
    {
        return [
            Company::firstOrCreate(['slug' => 'bmj'], ['name' => 'Bengkel Maju Jaya', 'status' => 'aktif']),
            Company::firstOrCreate(['slug' => 'tba'], ['name' => 'Toko Berkah Abadi', 'status' => 'aktif']),
        ];
    }

    /** @return list<Branch> */
    private function branches(Company $bengkel, Company $toko): array
    {
        $rows = [
            [$bengkel->id, 'PST', 'Pusat Ciledug', 'pusat', 'Asia/Jakarta', 'Jl. Ciledug Raya No. 45'],
            [$bengkel->id, 'CKR', 'Cabang Cikarang', 'cabang', 'Asia/Jakarta', 'Jl. Raya Cikarang KM 3'],
            [$bengkel->id, 'MKS', 'Cabang Makassar', 'cabang', 'Asia/Makassar', 'Jl. AP Pettarani No. 12'],
            [$bengkel->id, 'GDG', 'Gudang Bitung', 'gudang', 'Asia/Jakarta', 'Kawasan Industri Bitung Blok C'],
            [$toko->id, 'TBO', 'Toko Bontang', 'pusat', 'Asia/Makassar', 'Jl. Ahmad Yani No. 8'],
            [$toko->id, 'TJP', 'Toko Jayapura', 'cabang', 'Asia/Jayapura', 'Jl. Raya Abepura No. 21'],
        ];

        $out = [];
        foreach ($rows as [$companyId, $code, $name, $type, $tz, $address]) {
            $out[] = Branch::firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'timezone' => $tz, 'address' => $address,
                    'phone' => '02155000'.random_int(10, 99), 'status' => 'aktif']
            );
        }

        return $out;
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $rows = [
            'pemilik' => ['Bambang Wijaya', 'pemilik@demo.id', '0811000001', 'TK/0'],
            'manajer' => ['Sari Puspita', 'manajer@demo.id', '0811000002', 'K/0'],
            'kasir' => ['Budi Santoso', 'kasir@demo.id', '0811000003', 'TK/0'],
            'kasir2' => ['Dewi Rahmawati', 'kasir2@demo.id', '0811000004', 'TK/1'],
            'gudang' => ['Agus Setiawan', 'gudang@demo.id', '0811000005', 'K/1'],
            'teknisi' => ['Rian Hidayat', 'teknisi@demo.id', '0811000006', 'TK/0'],
            'hr' => ['Ratna Dewi', 'hr@demo.id', '0811000007', 'K/2'],
            'keuangan' => ['Hendra Gunawan', 'keuangan@demo.id', '0811000008', 'K/0'],
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

        foreach (PermissionCatalog::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $map = [
            'Pemilik' => 'pemilik',
            'Manajer Cabang' => 'manajer',
            'Kasir' => 'kasir',
            'Staf Gudang' => 'gudang',
            'Pelaksana Jasa' => 'teknisi',
            'HR' => 'hr',
            'Admin Keuangan' => 'keuangan',
        ];

        foreach ($map as $name => $key) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id]
            );
            $role->syncPermissions(RoleTemplates::permissionsFor($name, PermissionCatalog::all()));
            $users[$key]->assignRole($role);

            EmployeeAssignment::firstOrCreate(
                ['user_id' => $users[$key]->id, 'company_id' => $company->id, 'ended_at' => null],
                ['branch_id' => null, 'role_id' => $role->id, 'started_at' => now()->subMonths(3)->toDateString()]
            );
        }

        // kasir kedua tanpa penempatan cabang (akses penuh, untuk kontras demo)
        setPermissionsTeamId($company->id);
        $kasirRole = Role::query()->where('name', 'Kasir')->where('company_id', $company->id)->first();
        if ($kasirRole !== null) {
            $users['kasir2']->assignRole($kasirRole);
        }
    }

    /** @return list<Category> */
    private function categories(Company $company): array
    {
        $out = [];
        foreach (['Oli & Pelumas', 'Sparepart Mesin', 'Ban & Velg', 'Jasa Servis', 'Aksesoris', 'Minuman'] as $name) {
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
            'oli_ahm' => ['OLI-AHM', 'Oli AHM MPX 1L', 'barang', 'botol', $byName['Oli & Pelumas'], true, null, 0],
            'oli_yamalube' => ['OLI-YLB', 'Oli Yamalube Silver 0.8L', 'barang', 'botol', $byName['Oli & Pelumas'], true, null, 0],
            'kampas' => ['SP-BND', 'Kampas Rem Bendix Depan', 'barang', 'pcs', $byName['Sparepart Mesin'], true, null, 0],
            'aki' => ['SP-GS', 'Aki GS Astra 12V 5Ah', 'barang', 'pcs', $byName['Sparepart Mesin'], true, null, 0],
            'busi' => ['SP-NGK', 'Busi NGK CPR8EA', 'barang', 'pcs', $byName['Sparepart Mesin'], true, null, 0],
            'ban' => ['BN-IRC', 'Ban IRC Tubeless 90/90-14', 'barang', 'pcs', $byName['Ban & Velg'], true, null, 0],
            'helm' => ['AK-KYT', 'Helm KYT Half Face', 'barang', 'pcs', $byName['Aksesoris'], true, null, 0],
            'servis' => ['JS-RGN', 'Servis Ringan + Ganti Oli', 'jasa', null, $byName['Jasa Servis'], false, 'persen', 10],
            'tuneup' => ['JS-TUP', 'Tune Up + Injeksi', 'jasa', null, $byName['Jasa Servis'], false, 'nominal', 15000],
            'tambal' => ['JS-TBB', 'Tambal Ban Tubeless', 'jasa', null, $byName['Jasa Servis'], false, 'persen', 20],
        ];

        $out = [];
        foreach ($rows as $key => [$code, $name, $type, $unit, $categoryId, $track, $commType, $commValue]) {
            $out[$key] = Item::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'unit' => $unit, 'category_id' => $categoryId,
                    'track_stock' => $track, 'commission_type' => $commType, 'commission_value' => $commValue,
                    'status' => 'aktif']
            );
        }

        ItemConsumable::firstOrCreate(
            ['item_id' => $out['servis']->id, 'material_item_id' => $out['oli_ahm']->id], ['qty' => 1]
        );
        ItemConsumable::firstOrCreate(
            ['item_id' => $out['tuneup']->id, 'material_item_id' => $out['oli_yamalube']->id], ['qty' => 1]
        );
        ItemConsumable::firstOrCreate(
            ['item_id' => $out['servis']->id, 'material_item_id' => $out['busi']->id], ['qty' => 1]
        );

        return $out;
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function prices(array $branches, array $items): void
    {
        $base = [
            'oli_ahm' => 58000, 'oli_yamalube' => 52000, 'kampas' => 95000, 'aki' => 335000, 'busi' => 28000,
            'ban' => 285000, 'helm' => 195000, 'servis' => 85000, 'tuneup' => 150000, 'tambal' => 25000,
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
            ['PT Astra Otoparts', '02150689999', 'Jl. Raya Pegangsaan Dua KM 2,2, Jakarta'],
            ['PT Indoparts Makmur', '02155998877', 'Jl. Industri No. 8, Tangerang'],
            ['CV Barokah Jaya', '081233344455', 'Jl. Raya Cikarang KM 3, Bekasi'],
            ['UD Lancar Abadi', '085611122233', 'Pasar Induk Cibitung Blok F No. 11'],
            ['Toko Sinar Oli', '081244455566', 'Jl. Ciledug Raya No. 45'],
            ['PT Ban Nusantara', '03144556677', 'Jl. Rungkut Industri IV No. 5, Surabaya'],
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
            ['Dudi Herlambang', '0812000001', 'Ciledug, Tangerang', 'Motor Beat, rutin servis tiap 2 bulan'],
            ['Sari Wahyuni', '0812000002', 'Cikarang, Bekasi', null],
            ['Acong Wijaya', '0812000003', null, 'Reseller sparepart'],
            ['Eko Prasetyo', null, 'Bitung, Tangerang', null],
            ['PT Angkasa Trans', '02177788899', 'Jl. Bandara Soetta KM 2', 'Armada 12 unit, tempo 14 hari'],
            ['Bengkel Rejeki', '0812000006', 'Ciledug', 'Reseller, ambil tiap Jumat'],
            ['Wulan Purnama', '0812000007', 'Makassar', null],
            ['H. Mahmud Basri', '0812000008', 'Ciledug', 'Langganan sejak 2019'],
        ] as [$name, $phone, $address, $notes]) {
            $data = ['name' => $name, 'address' => $address, 'notes' => $notes];
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
        $qtys = ['oli_ahm' => 40, 'oli_yamalube' => 35, 'kampas' => 20, 'aki' => 12, 'busi' => 50, 'ban' => 15, 'helm' => 8];

        foreach ($qtys as $key => $qty) {
            StockMovement::firstOrCreate(
                ['company_id' => $company->id, 'branch_id' => $branch->id, 'item_id' => $items[$key]->id,
                    'type' => 'pembelian', 'qty' => $qty],
                ['unit_cost' => 30000, 'hpp_amount' => $qty * 30000, 'balance_after' => $qty, 'note' => 'Stok awal']
            );
            StockBalance::firstOrCreate(
                ['branch_id' => $branch->id, 'item_id' => $items[$key]->id],
                ['company_id' => $company->id, 'qty' => $qty, 'avg_cost' => 30000]
            );
        }

        // mutasi keluar kemarin agar riwayat tidak kosong
        StockMovement::firstOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branch->id, 'item_id' => $items['oli_ahm']->id,
                'type' => 'penjualan', 'qty' => -3],
            ['unit_cost' => null, 'hpp_amount' => 90000, 'balance_after' => 37, 'note' => 'Terjual kemarin']
        );
    }

    /** @param list<Branch> $branches @param array<string, Item> $items */
    private function transfers(Company $company, array $branches, array $items): void
    {
        $statuses = ['draft', 'diajukan', 'disetujui', 'dikirim', 'diterima', 'dibatalkan'];
        $notes = [
            'Minta oli ke Cikarang, stok menipis',
            'Kampas rem untuk promo akhir bulan',
            'Aki cadangan Makassar',
            'Ban dikirim via ekspedisi Sinar',
            'Sudah sampai Cikarang kemarin',
            'Batal: salah kode barang',
        ];

        foreach ($statuses as $i => $status) {
            $transfer = StockTransfer::firstOrCreate(
                ['company_id' => $company->id, 'from_branch_id' => $branches[0]->id,
                    'to_branch_id' => $branches[1]->id, 'status' => $status, 'note' => $notes[$i]],
                ['shipped_at' => in_array($status, ['dikirim', 'diterima'], true) ? now()->subDay() : null,
                    'received_at' => $status === 'diterima' ? now()->subDay() : null]
            );
            $transfer->items()->firstOrCreate(
                ['item_id' => $items['oli_ahm']->id],
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
                'status' => $status, 'note' => 'Opname rutin minggu ke-'.($i + 1),
            ]);
            $opname->items()->create(['item_id' => $items['oli_ahm']->id, 'qty_system' => 40, 'qty_counted' => 40 - $i]);
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
                'supplier_id' => $suppliers[$i]->id, 'status' => $status, 'note' => 'Restock rutin '.($i + 1),
            ]);
            $qty = 10 + $i * 5;
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id, 'item_id' => $items['oli_ahm']->id,
                'qty_ordered' => $qty, 'qty_received' => $status === 'selesai' ? $qty : 0, 'unit_price' => 30000,
            ]);

            if (in_array($status, ['dipesan', 'selesai'], true)) {
                $receipt = GoodsReceipt::create([
                    'company_id' => $company->id, 'purchase_order_id' => $order->id,
                    'due_at' => now()->addDays(14)->toDateString(), 'note' => 'Diterima gudang Bitung',
                ]);
                $receipt->items()->create([
                    'item_id' => $items['oli_ahm']->id, 'qty_received' => $qty, 'unit_cost' => 30000,
                ]);
                SupplierBill::firstOrCreate(
                    ['purchase_order_id' => $order->id],
                    ['company_id' => $company->id, 'amount' => $qty * 30000,
                        'paid_amount' => $status === 'selesai' ? $qty * 30000 : 100000,
                        'due_at' => now()->addDays(14)->toDateString()]
                );
            }
        }

        $bill = SupplierBill::query()->where('company_id', $company->id)->first();
        if ($bill !== null) {
            foreach ([50000, 50000] as $amount) {
                SupplierBillPayment::firstOrCreate(
                    ['supplier_bill_id' => $bill->id, 'amount' => $amount, 'paid_at' => now()->subDays(2)->toDateString()],
                    ['method' => 'transfer', 'note' => 'Cicilan via BCA']
                );
            }
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users @param array<string, Item> $items @param list<Customer> $customers */
    private function sales(Company $company, array $branches, array $users, array $items, array $customers): void
    {
        $branch = $branches[0];
        $shift = CashShift::firstOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $users['kasir']->id, 'status' => 'terbuka'],
            ['opening_cash' => 200000, 'opened_at' => now()->subHours(3)]
        );
        CashShift::firstOrCreate(
            ['company_id' => $company->id, 'branch_id' => $branches[1]->id, 'user_id' => $users['kasir2']->id, 'status' => 'ditutup'],
            ['opening_cash' => 150000, 'closing_cash_counted' => 480000, 'closing_cash_system' => 475000,
                'difference' => 5000, 'opened_at' => now()->subDay(), 'closed_at' => now()->subDay()]
        );

        $combos = [
            ['lunas', [['oli_ahm', 2, 0, 0]], [['tunai', 116000, 120000, null]]],
            ['lunas', [['servis', 1, 10, 0]], [['tunai', 76500, 80000, null]]],
            ['lunas', [['kampas', 1, 0, 5000], ['oli_yamalube', 1, 0, 0]], [['transfer', 137000, 0, 'REF-BCA-001']]],
            ['lunas', [['ban', 2, 5, 0]], [['qris', 270750, 0, 'QRIS-8821']]],
            ['lunas', [['helm', 1, 0, 0], ['busi', 2, 0, 0]], [['tunai', 251000, 260000, null]]],
            ['void', [['aki', 1, 0, 0]], [['tunai', 335000, 335000, null]]],
            ['retur', [['kampas', 2, 0, 0]], [['tunai', 190000, 190000, null]]],
            ['lunas', [['tuneup', 1, 0, 0]], [['ewallet', 150000, 0, 'OVO-771']]],
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

            $sale = Sale::firstOrCreate(
                ['branch_id' => $branch->id, 'number' => sprintf('%s-%s-%04d', $branch->code, now()->format('ymd'), $n)],
                ['company_id' => $company->id, 'shift_id' => $shift->id, 'user_id' => $users['kasir']->id,
                    'status' => $status, 'subtotal' => $subtotal, 'discount_total' => $discount,
                    'tax_total' => 0, 'grand_total' => $subtotal - $discount,
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
                $sale->payments()->firstOrCreate(
                    ['sale_id' => $sale->id, 'method' => $method, 'amount' => $amount],
                    ['tendered' => $tendered > 0 ? $tendered : null, 'reference' => $ref]
                );
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
                ['company_id' => $company->id, 'reason' => 'Salah pencet nominal oleh kasir baru',
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
                ['company_id' => $company->id, 'reason' => 'Kampas tidak cocok dengan motor pelanggan',
                    'refund_method' => 'tunai', 'refund_amount' => 90000, 'status' => 'disetujui']
            );
            $firstItem = $returSale->items()->first();
            if ($firstItem !== null) {
                $retur->items()->firstOrCreate(
                    ['sale_item_id' => $firstItem->id],
                    ['item_id' => $firstItem->item_id, 'qty' => 1, 'condition' => 'rusak', 'refund_amount' => 90000]
                );
            }
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users @param array<string, Item> $items @param list<Customer> $customers */
    private function services(Company $company, array $branches, array $users, array $items, array $customers): void
    {
        $statuses = ['baru', 'diproses', 'selesai', 'dibayar', 'dibatalkan', 'baru', 'diproses'];
        $notes = [
            'Beat brebet, minta servis ringan',
            'Vario ganti oli + tune up',
            'NMax turun mesin tahap 1',
            'Sudah diambil pelanggan kemarin',
            'Batal: pelanggan pindah bengkel',
            'Scoopy servis + tambal ban',
            'Aerox keluhan CVT berisik',
        ];

        foreach ($statuses as $i => $status) {
            $order = ServiceOrder::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'customer_id' => $customers[$i]->id,
                'assignee_id' => in_array($status, ['diproses', 'selesai', 'dibayar'], true) ? $users['teknisi']->id : null,
                'status' => $status, 'deposit_amount' => $i % 2 === 0 ? 20000 : 0, 'note' => $notes[$i],
            ]);
            $order->items()->create([
                'item_id' => $items['servis']->id, 'qty' => 1, 'price' => 85000, 'commission_amount' => 8500,
            ]);
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users */
    private function hr(Company $company, array $branches, array $users): void
    {
        foreach ([
            ['Pagi', '08:00', '16:00', 15],
            ['Siang', '13:00', '21:00', 15],
            ['Full Day', '08:00', '21:00', 30],
            ['Jaga Malam', '21:00', '06:00', 0],
        ] as [$name, $start, $end, $tol]) {
            WorkShift::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['starts_at' => $start, 'ends_at' => $end, 'late_tolerance_minutes' => $tol]
            );
        }
        $pagi = WorkShift::query()->where('company_id', $company->id)->where('name', 'Pagi')->first();
        $siang = WorkShift::query()->where('company_id', $company->id)->where('name', 'Siang')->first();

        foreach (range(0, 6) as $weekday) {
            SchedulePattern::firstOrCreate(
                ['user_id' => $users['kasir']->id, 'weekday' => $weekday],
                ['company_id' => $company->id, 'work_shift_id' => $weekday === 0 ? null : $pagi->id]
            );
            SchedulePattern::firstOrCreate(
                ['user_id' => $users['teknisi']->id, 'weekday' => $weekday],
                ['company_id' => $company->id, 'work_shift_id' => $weekday === 5 ? $siang->id : $pagi->id]
            );
        }

        ScheduleOverride::firstOrCreate(
            ['user_id' => $users['teknisi']->id, 'date' => now()->addDays(2)->toDateString()],
            ['company_id' => $company->id, 'work_shift_id' => $siang->id, 'note' => 'Tukar shift dengan Agus']
        );
        ScheduleOverride::firstOrCreate(
            ['user_id' => $users['kasir']->id, 'date' => now()->addDays(5)->toDateString()],
            ['company_id' => $company->id, 'work_shift_id' => null, 'note' => 'Libur hajatan keluarga']
        );

        foreach ([
            [null, '2026-12-25', 'Natal'],
            [null, '2026-08-17', 'Kemerdekaan RI'],
            [$company->id, now()->subDays(3)->toDateString(), 'Ulang tahun perusahaan ke-7'],
        ] as [$companyId, $date, $name]) {
            Holiday::firstOrCreate(['company_id' => $companyId, 'date' => $date], ['name' => $name]);
        }

        // absensi hari ini agar dasbor & riwayat berisi
        $hariIni = now()->toDateString();
        $hadir = [
            ['kasir', 'hadir', '07:55', 0],
            ['kasir2', 'terlambat', '08:25', 25],
            ['gudang', 'hadir', '07:50', 0],
            ['teknisi', 'hadir', '08:02', 0],
            ['hr', 'izin', null, 0],
        ];
        foreach ($hadir as [$key, $status, $jam, $telat]) {
            Attendance::firstOrCreate(
                ['user_id' => $users[$key]->id, 'branch_id' => $branches[0]->id, 'date' => $hariIni],
                ['company_id' => $company->id, 'status' => $status,
                    'clock_in_at' => $jam === null ? null : now()->toDateString().' '.$jam.':00',
                    'late_minutes' => $telat, 'clock_in_device' => 'demo-'.$key]
            );
        }
        // riwayat kemarin & lusa
        $statuses = ['hadir', 'hadir', 'terlambat', 'alpa'];
        $i = 0;
        foreach (['kasir', 'kasir2', 'gudang', 'teknisi'] as $key) {
            foreach ([1, 2] as $daysAgo) {
                Attendance::firstOrCreate(
                    ['user_id' => $users[$key]->id, 'branch_id' => $branches[0]->id,
                        'date' => now()->subDays($daysAgo)->toDateString()],
                    ['company_id' => $company->id, 'status' => $statuses[$i % count($statuses)],
                        'clock_in_at' => now()->subDays($daysAgo)->toDateString().' 08:05:00',
                        'late_minutes' => $statuses[$i % count($statuses)] === 'terlambat' ? 20 : 0,
                        'clock_in_device' => 'demo-'.$key]
                );
                $i++;
            }
        }

        foreach (['kasir', 'gudang', 'teknisi', 'kasir2', 'hr'] as $key) {
            UserDevice::firstOrCreate(['user_id' => $users[$key]->id], ['device_id' => 'demo-'.$key]);
            UserDeviceToken::firstOrCreate(
                ['user_id' => $users[$key]->id, 'token' => 'demo-fcm-'.$key], ['platform' => 'android']
            );
            NotificationPreference::firstOrCreate(
                ['user_id' => $users[$key]->id, 'type' => 'po.disetujui'], ['enabled' => true]
            );
        }
        NotificationPreference::firstOrCreate(
            ['user_id' => $users['manajer']->id, 'type' => 'approval.diajukan'], ['enabled' => true]
        );

        $types = ['izin', 'sakit', 'cuti', 'izin', 'cuti', 'sakit'];
        $states = ['diajukan', 'disetujui', 'ditolak', 'diajukan', 'disetujui', 'diajukan'];
        $reasons = [
            'Kontrol gigi ke RS Hermina',
            'Demam + surat dokter klinik',
            'Hajatan kakak di kampung',
            'Antar anak imunisasi',
            'Liburan keluarga ke Anyer',
            'Flu berat, istirahat 2 hari',
        ];
        foreach ($types as $i => $type) {
            LeaveRequest::create([
                'company_id' => $company->id, 'user_id' => $users['kasir']->id, 'type' => $type,
                'from_date' => now()->addDays($i + 2)->toDateString(),
                'to_date' => now()->addDays($i + 3)->toDateString(),
                'reason' => $reasons[$i], 'status' => $states[$i],
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

        foreach (['kasir', 'teknisi', 'gudang'] as $key) {
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
            ['Gaji Agustus 2026', '2026-08-01', '2026-08-31', 'dibayar'],
            ['Gaji September 2026', '2026-09-01', '2026-09-30', 'disetujui'],
            ['Gaji Oktober 2026', now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), 'draft'],
            ['THR Lebaran 2026', '2026-03-01', '2026-03-31', 'review'],
        ];

        foreach ($periods as [$name, $from, $to, $status]) {
            $period = PayrollPeriod::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['date_from' => $from, 'date_to' => $to, 'divisor' => 25, 'status' => $status]
            );
            PayrollLine::firstOrCreate(
                ['payroll_period_id' => $period->id, 'user_id' => $users['kasir']->id, 'label' => 'Gaji pokok'],
                ['salary_component_id' => $compModels['Gaji pokok'][0]->id, 'amount' => 5000000, 'is_manual' => false]
            );
            PayrollLine::firstOrCreate(
                ['payroll_period_id' => $period->id, 'user_id' => $users['kasir']->id,
                    'label' => 'BPJS karyawan (1% Kes + 2% JHT + 1% JP)'],
                ['salary_component_id' => null, 'amount' => -200000, 'is_manual' => false]
            );
            PayrollLine::firstOrCreate(
                ['payroll_period_id' => $period->id, 'user_id' => $users['teknisi']->id, 'label' => 'Gaji pokok'],
                ['salary_component_id' => $compModels['Gaji pokok'][0]->id, 'amount' => 4500000, 'is_manual' => false]
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
            ['sale', 9001, 'Nota PST-261012-0001', ['1110' => [116000, 0], '4100' => [0, 116000]]],
            ['expense', 9002, 'Biaya: Listrik PLN Oktober', ['6200' => [450000, 0], '1110' => [0, 450000]]],
            ['po_receipt', 9003, 'Penerimaan PO #1 Astra', ['1310' => [300000, 0], '2110' => [0, 300000]]],
            ['bill_payment', 9004, 'Bayar hutang PO #1', ['2110' => [100000, 0], '1120' => [0, 100000]]],
            ['receivable', 9005, 'Piutang #1 Bengkel Rejeki', ['1210' => [250000, 0], '4100' => [0, 250000]]],
            ['receivable_payment', 9006, 'Terima piutang #1', ['1110' => [50000, 0], '1210' => [0, 50000]]],
            ['payroll_accrue', 9007, 'Beban gaji Juni', ['6100' => [14500000, 0], '2140' => [0, 14500000]]],
            ['payroll_pay', 9008, 'Bayar gaji Juni via BCA', ['2140' => [14500000, 0], '1110' => [0, 14500000]]],
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
        foreach (['Listrik & Air', 'Gaji & THR', 'ATK & Cetak', 'Sewa Ruko', 'Bensin Operasional', 'Iklan Sosmed'] as $name) {
            $cats[] = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => $name]);
        }

        $rows = [
            ['Listrik PLN Oktober', 450000, 'transfer', 'disetujui', 2],
            ['Galon + gas elpiji', 120000, 'tunai', 'disetujui', 5],
            ['Cetak brosur promo', 75000, 'tunai', 'ditolak', 6],
            ['Bensin antar barang', 100000, 'tunai', 'disetujui', 1],
            ['Perpanjang domain', 200000, 'transfer', 'draft', 3],
            ['Konsumsi lembur', 85000, 'tunai', 'disetujui', 0],
            ['Iklan FB Ads', 300000, 'transfer', 'draft', 4],
            ['Servis AC kantor', 500000, 'transfer', 'ditolak', 7],
        ];
        foreach ($rows as $i => [$desc, $amount, $method, $status, $daysAgo]) {
            Expense::create([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'expense_category_id' => $cats[$i % count($cats)]->id,
                'amount' => $amount, 'method' => $method,
                'date' => now()->subDays($daysAgo)->toDateString(),
                'description' => $desc, 'status' => $status,
            ]);
        }

        for ($i = 0; $i < 6; $i++) {
            $receivable = Receivable::create([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'customer_id' => $i % 2 === 0 ? $customers[$i]->id : null,
                'amount' => 200000 + $i * 50000, 'paid_amount' => $i < 2 ? 50000 : 0,
                'due_at' => now()->addDays(7 - $i * 5)->toDateString(), 'note' => 'Bon servis '.($i + 1),
            ]);
            if ($i < 3) {
                ReceivablePayment::create([
                    'receivable_id' => $receivable->id, 'amount' => 50000,
                    'method' => ['tunai', 'transfer', 'qris'][$i % 3],
                    'paid_at' => now()->subDays(1)->toDateString(), 'note' => 'Cicilan via kasir',
                ]);
            }
        }
    }

    private function billing(Company $bengkel, Company $toko): void
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
            ['company_id' => $bengkel->id],
            ['plan_id' => $pro->id, 'status' => 'aktif', 'ends_at' => now()->addDays(20)->toDateString()]
        );
        Subscription::firstOrCreate(
            ['company_id' => $toko->id],
            ['plan_id' => $dasar->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays(9)->toDateString()]
        );

        $states = [
            [$bengkel->id, $dasar->id, 'menunggu', null, 99000, 'Tagihan bulan berjalan'],
            [$bengkel->id, $pro->id, 'lunas', -30, 299000, 'Upgrade ke Pro'],
            [$bengkel->id, $dasar->id, 'lunas', -60, 99000, 'Bulan lalu'],
            [$toko->id, $dasar->id, 'menunggu', null, 99000, 'Tagihan pertama'],
            [$bengkel->id, $pro->id, 'lunas', -30, 299000, 'Perpanjangan Pro'],
            [$toko->id, $dasar->id, 'lunas', -90, 0, 'Masa gratis awal'],
        ];
        foreach ($states as [$companyId, $planId, $status, $paidAgo, $amount, $note]) {
            SubscriptionInvoice::create([
                'company_id' => $companyId, 'plan_id' => $planId, 'amount' => $amount, 'status' => $status,
                'due_at' => now()->addDays($status === 'menunggu' ? 7 : -20)->toDateString(),
                'paid_at' => $paidAgo === null ? null : now()->addDays($paidAgo)->toDateString(), 'note' => $note,
            ]);
        }
    }

    /** @param list<Branch> $branches @param array<string, User> $users */
    private function misc(Company $company, array $branches, array $users): void
    {
        foreach ([
            [$users['kasir']->id, 'sale.void', 'sale', 6, ['status' => 'lunas'], ['status' => 'void']],
            [$users['pemilik']->id, 'po.approve', 'purchase_order', 2, ['status' => 'diajukan'], ['status' => 'disetujui']],
            [$users['manajer']->id, 'price.set', 'item', 3, ['price' => 95000], ['price' => 100000]],
            [$users['keuangan']->id, 'biaya.setujui', 'expense', 1, ['status' => 'draft'], ['status' => 'disetujui']],
            [$users['hr']->id, 'payroll.pay', 'payroll_period', 1, ['status' => 'disetujui'], ['status' => 'dibayar']],
            [null, 'attendance.mark-absent', 'attendance', 9, null, ['status' => 'alpa']],
            [$users['gudang']->id, 'stock.transfer.approve', 'stock_transfer', 3, ['status' => 'diajukan'], ['status' => 'disetujui']],
            [$users['pemilik']->id, 'approval.setujui', 'approval', 1, ['status' => 'diajukan'], ['status' => 'disetujui']],
        ] as [$userId, $action, $entityType, $entityId, $before, $after]) {
            AuditLog::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id, 'user_id' => $userId,
                'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId,
                'before' => $before, 'after' => $after, 'ip' => '127.0.0.1',
            ]);
        }

        $ownerId = $users['pemilik']->id;
        foreach ([
            ['po.disetujui', 'PO oli ke Astra disetujui', null, true],
            ['sale.void', 'Nota salah nominal dibatalkan', null, false],
            ['transfer.diajukan', 'Minta oli ke Cikarang', null, false],
            ['approval.diajukan', 'Void nota menunggu keputusan Anda', '/approvals/1', false],
            ['shift.selisih', 'Selisih kas Rp5.000 di Cikarang', null, false],
            ['payroll.pay', 'Gaji September dibayar', null, true],
            ['sale.retur', 'Retur kampas Rp90.000', null, true],
            ['opname.disetujui', 'Opname minggu ke-3 disetujui', null, false],
        ] as [$type, $title, $link, $read]) {
            AppNotification::create([
                'company_id' => $company->id, 'user_id' => $ownerId,
                'type' => $type, 'title' => $title, 'body' => 'Contoh pemberitahuan demo', 'link' => $link,
                'read_at' => $read ? now()->subHours(3) : null,
            ]);
        }

        $ym = now()->format('Y-m');
        foreach ([
            ['2026-08', 'tutup'], ['2026-09', 'tutup'],
            [$ym, 'buka'], [now()->addMonth()->format('Y-m'), 'buka'],
        ] as [$label, $status]) {
            [$year, $month] = explode('-', $label);
            AccountingPeriod::firstOrCreate(
                ['company_id' => $company->id, 'year' => (int) $year, 'month' => (int) $month],
                ['status' => $status, 'closed_at' => $status === 'tutup' ? now() : null]
            );
        }

        $actions = [
            ['sale.void', 'sale', 6, 'diajukan', ['reason' => 'Salah pencet nominal, pelanggan protes']],
            ['sale.discount', null, null, 'diajukan', ['branch_id' => $branches[0]->id, 'note' => 'Diskon gathering 10%']],
            ['sale.refund', 'sale', 7, 'disetujui', ['reason' => 'Kampas tidak cocok']],
            ['po.approve', 'purchase_order', 2, 'disetujui', []],
            ['transfer.approve', 'stock_transfer', 2, 'diajukan', []],
            ['opname.approve', 'stock_opname', 2, 'ditolak', []],
        ];
        foreach ($actions as [$action, $entityType, $entityId, $status, $payload]) {
            Approval::create([
                'company_id' => $company->id, 'branch_id' => $branches[0]->id,
                'entity_type' => $entityType, 'entity_id' => $entityId, 'action' => $action,
                'status' => $status, 'payload' => $payload === [] ? null : $payload,
                'requested_by' => $users['kasir']->id,
                'decided_by' => $status === 'diajukan' ? null : $users['pemilik']->id,
                'decided_at' => $status === 'diajukan' ? null : now()->subHours(5),
                'note' => 'Contoh pengajuan demo',
            ]);
        }

        foreach (range(0, 7) as $daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();
            DailyBranchSummary::firstOrCreate(
                ['company_id' => $company->id, 'branch_id' => $branches[$daysAgo % 2]->id, 'date' => $date],
                ['omzet' => 500000 + $daysAgo * 100000, 'transactions' => 8 + $daysAgo,
                    'hpp_total' => 200000, 'discount_total' => 15000, 'tax_total' => 10000,
                    'cash_tunai' => 300000, 'refund_total' => $daysAgo === 3 ? 90000 : 0,
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
