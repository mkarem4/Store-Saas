<?php

namespace Database\Seeders;

use App\Enums\AttendanceMode;
use App\Enums\BusinessStatus;
use App\Enums\BusinessTypeKey;
use App\Enums\ExpenseCategoryKey;
use App\Enums\ExpenseRecurrence;
use App\Enums\ExpenseSource;
use App\Enums\FulfillmentType;
use App\Enums\JewelryMetal;
use App\Enums\JewelryPricingMode;
use App\Enums\MakingType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayType;
use App\Enums\ProductUnit;
use App\Enums\RentalRateType;
use App\Enums\RentalStatus;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Enums\UserRole;
use App\Enums\VehiclePurpose;
use App\Enums\VehicleStatus;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoldPrice;
use App\Models\JewelryAttribute;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RentalContract;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use App\Support\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@store.test'],
            [
                'name' => 'Platform Owner',
                'password' => 'password',
                'role' => UserRole::Owner,
                'locale' => 'en',
                'is_active' => true,
            ],
        );

        $types = BusinessType::query()->orderBy('sort_order')->get();

        foreach ($types as $type) {
            $business = $this->createBusiness($type);

            $this->createOwnerAndStaff($business, $type);
            $this->createCatalog($business, $type);
            $this->createExpenses($business);

            if ($type->key === BusinessTypeKey::CarShowroom->value) {
                $this->createCarShowroom($business);
            }

            if ($type->key === BusinessTypeKey::CarRental->value) {
                $this->createCarRental($business);
            }

            if ($type->key === BusinessTypeKey::Jewelry->value) {
                $this->createGoldPrices($business);
            }

            $this->createSales($business);
        }

        $this->command?->info("Demo seeded. Platform owner: {$owner->email} / password");
    }

    private function createBusiness(BusinessType $type): Business
    {
        $names = [
            BusinessTypeKey::CarShowroom->value => ['en' => 'Prime Motors', 'ar' => 'برايم موتورز'],
            BusinessTypeKey::Electronics->value => ['en' => 'Tech Hub', 'ar' => 'تك هب'],
            BusinessTypeKey::CarRental->value => ['en' => 'City Rentals', 'ar' => 'سيتي لتأجير السيارات'],
            BusinessTypeKey::Jewelry->value => ['en' => 'Golden House', 'ar' => 'بيت الذهب'],
            BusinessTypeKey::Perfume->value => ['en' => 'Scent Studio', 'ar' => 'استوديو العطور'],
            BusinessTypeKey::Clothing->value => ['en' => 'Urban Threads', 'ar' => 'أوربان ثريدز'],
            BusinessTypeKey::Grocery->value => ['en' => 'Fresh Market', 'ar' => 'فريش ماركت'],
            BusinessTypeKey::Restaurant->value => ['en' => 'Olive & Flame', 'ar' => 'أوليف آند فليم'],
        ];

        $name = $names[$type->key] ?? ['en' => Str::title($type->key), 'ar' => $type->translatedName()];

        return Business::query()->updateOrCreate(
            ['slug' => $type->key],
            [
                'business_type_id' => $type->id,
                'name' => $name,
                'description' => [
                    'en' => "Demo {$type->translatedName('en')} business for the pilot.",
                    'ar' => "محل تجريبي من نوع {$type->translatedName('ar')} للـPilot.",
                ],
                'phone' => '+1 (555) 010-'.str_pad((string) $type->id, 4, '0', STR_PAD_LEFT),
                'email' => "hello@{$this->domain($type)}",
                'address_line' => '120 Main Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'latitude' => 30.2672 + $type->id * 0.004,
                'longitude' => -97.7431,
                'currency' => 'USD',
                'currency_decimals' => 2,
                'timezone' => 'America/Chicago',
                'status' => BusinessStatus::Active,
                'allowed_ips' => ['127.0.0.1', '::1'],
                'attendance_mode' => AttendanceMode::Ip,
                'geofence_radius_m' => 150,
                'settings' => [
                    ...Business::DEFAULT_SETTINGS,
                    'pickup_enabled' => true,
                    'delivery_enabled' => true,
                    'delivery_base_fee_cents' => 200,
                    'delivery_fee_per_km_cents' => 150,
                    'delivery_max_km' => 15,
                    'minimum_order_cents' => 1500,
                ],
            ],
        );
    }

    private function createOwnerAndStaff(Business $business, BusinessType $type): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);

        $ownerRole = Role::findOrCreate(UserRole::BusinessOwner->value, 'web');
        $ownerRole->syncPermissions(Permission::businessOwner());

        $staffRole = Role::findOrCreate(UserRole::Staff->value, 'web');
        $staffRole->syncPermissions(Permission::staff());

        $owner = User::query()->updateOrCreate(
            ['email' => "owner@{$this->domain($type)}"],
            [
                'business_id' => $business->id,
                'name' => $business->translated('name', 'en').' Owner',
                'password' => 'password',
                'role' => UserRole::BusinessOwner,
                'locale' => 'en',
                'is_active' => true,
            ],
        );
        $owner->syncRoles([$ownerRole]);

        $staff = User::query()->updateOrCreate(
            ['email' => "staff@{$this->domain($type)}"],
            [
                'business_id' => $business->id,
                'name' => $business->translated('name', 'en').' Staff',
                'password' => 'password',
                'role' => UserRole::Staff,
                'locale' => 'en',
                'is_active' => true,
            ],
        );
        $staff->syncRoles([$staffRole]);

        Employee::query()->updateOrCreate(
            ['business_id' => $business->id, 'user_id' => $staff->id],
            [
                'name' => $staff->name,
                'email' => $staff->email,
                'position' => 'Sales associate',
                'pay_type' => PayType::Hourly,
                'hourly_rate_cents' => 1800,
                'monthly_salary_cents' => 0,
                'commission_rate_bps' => in_array($type->key, [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::Jewelry->value], true) ? 500 : 0,
                'hire_date' => now()->subMonths(6)->toDateString(),
                'is_active' => true,
            ],
        );

        Employee::query()->updateOrCreate(
            ['business_id' => $business->id, 'email' => "manager@{$this->domain($type)}"],
            [
                'name' => 'Store Manager',
                'position' => 'Manager',
                'pay_type' => PayType::Monthly,
                'monthly_salary_cents' => 450000,
                'hourly_rate_cents' => 0,
                'commission_rate_bps' => 0,
                'hire_date' => now()->subYear()->toDateString(),
                'is_active' => true,
            ],
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }

    private function createCatalog(Business $business, BusinessType $type): void
    {
        $categories = match ($type->key) {
            BusinessTypeKey::Clothing->value => [['en' => 'Men', 'ar' => 'رجالي'], ['en' => 'Women', 'ar' => 'نسائي'], ['en' => 'Accessories', 'ar' => 'إكسسوارات']],
            BusinessTypeKey::Electronics->value => [['en' => 'Phones', 'ar' => 'هواتف'], ['en' => 'Audio', 'ar' => 'صوتيات'], ['en' => 'Accessories', 'ar' => 'إكسسوارات']],
            BusinessTypeKey::Grocery->value => [['en' => 'Pantry', 'ar' => 'بقالة'], ['en' => 'Dairy', 'ar' => 'ألبان'], ['en' => 'Produce', 'ar' => 'خضروات وفواكه']],
            BusinessTypeKey::Perfume->value => [['en' => 'Men', 'ar' => 'رجالي'], ['en' => 'Women', 'ar' => 'نسائي'], ['en' => 'Unisex', 'ar' => 'للجنسين']],
            BusinessTypeKey::Jewelry->value => [['en' => 'Rings', 'ar' => 'خواتم'], ['en' => 'Necklaces', 'ar' => 'قلادات'], ['en' => 'Bracelets', 'ar' => 'أساور']],
            BusinessTypeKey::Restaurant->value => [['en' => 'Starters', 'ar' => 'مقبلات'], ['en' => 'Mains', 'ar' => 'أطباق رئيسية'], ['en' => 'Drinks', 'ar' => 'مشروبات']],
            BusinessTypeKey::CarShowroom->value => [['en' => 'Sedans', 'ar' => 'سيدان'], ['en' => 'SUVs', 'ar' => 'دفع رباعي']],
            BusinessTypeKey::CarRental->value => [['en' => 'Economy', 'ar' => 'اقتصادية'], ['en' => 'Premium', 'ar' => 'فاخرة']],
            default => [['en' => 'General', 'ar' => 'عام']],
        };

        $categoryModels = collect($categories)->map(fn (array $name, int $index): Category => Category::query()->updateOrCreate(
            ['business_id' => $business->id, 'slug' => Str::slug($name['en'])],
            ['name' => $name, 'sort_order' => $index, 'is_active' => true],
        ));

        $catalog = match ($type->key) {
            BusinessTypeKey::Clothing->value => [
                ['Classic T-Shirt', 'تي شيرت كلاسيك', 1200, 2500, [['S', 'M', 'L'], ProductUnit::Piece->value]],
                ['Slim Jeans', 'جينز سليم', 2200, 4500, [['30', '32', '34'], ProductUnit::Piece->value]],
                ['Bomber Jacket', 'جاكيت بومبر', 3500, 7900, [['M', 'L'], ProductUnit::Piece->value]],
            ],
            BusinessTypeKey::Electronics->value => [
                ['Smartphone X', 'هاتف ذكي X', 45000, 69900, [['128GB', '256GB'], ProductUnit::Piece->value]],
                ['Wireless Earbuds', 'سماعات لاسلكية', 3500, 7900, [['White', 'Black'], ProductUnit::Piece->value]],
                ['Fast Charger', 'شاحن سريع', 900, 1999, [['20W'], ProductUnit::Piece->value]],
            ],
            BusinessTypeKey::Grocery->value => [
                ['Basmati Rice', 'أرز بسمتي', 180, 299, [['1kg'], ProductUnit::Kilogram->value]],
                ['Fresh Milk', 'حليب طازج', 120, 249, [['1L'], ProductUnit::Piece->value]],
                ['Free Range Eggs', 'بيض بلدي', 300, 549, [['12pcs'], ProductUnit::Carton->value]],
            ],
            BusinessTypeKey::Perfume->value => [
                ['Oud Royale', 'عود رويال', 3000, 6500, [['50ml', '100ml'], ProductUnit::Piece->value]],
                ['Citrus Bloom', 'حمضيات بلوم', 1800, 3900, [['30ml', '50ml'], ProductUnit::Piece->value]],
            ],
            BusinessTypeKey::Jewelry->value => [
                ['Gold Ring', 'خاتم ذهب', 0, 0, [['18K'], ProductUnit::Piece->value]],
                ['Gold Necklace', 'قلادة ذهب', 0, 0, [['21K'], ProductUnit::Piece->value]],
                ['Silver Bracelet', 'أسورة فضة', 4500, 8900, [['925'], ProductUnit::Piece->value]],
            ],
            BusinessTypeKey::Restaurant->value => [
                ['Hummus', 'حمص', 300, 650, [['Regular'], ProductUnit::Piece->value]],
                ['Grilled Chicken', 'دجاج مشوي', 700, 1450, [['Regular', 'Large'], ProductUnit::Piece->value]],
                ['Lemonade', 'ليموناضة', 100, 350, [['Regular'], ProductUnit::Piece->value]],
                ['Chocolate Cake', 'كيك شوكولاتة', 500, 899, [['Slice'], ProductUnit::Piece->value]],
            ],
            BusinessTypeKey::CarShowroom->value => [],
            BusinessTypeKey::CarRental->value => [],
            default => [],
        };

        foreach ($catalog as $index => [$nameEn, $nameAr, $cost, $retail, [$variantNames, $unit]]) {
            $product = Product::query()->updateOrCreate(
                ['business_id' => $business->id, 'sku' => strtoupper($type->key).'-'.($index + 1)],
                [
                    'category_id' => $categoryModels[$index % $categoryModels->count()]->id,
                    'name' => ['en' => $nameEn, 'ar' => $nameAr],
                    'description' => ['en' => "{$nameEn} — demo product.", 'ar' => "{$nameAr} — منتج تجريبي."],
                    'barcode' => '200000'.str_pad((string) ($business->id * 100 + $index), 7, '0', STR_PAD_LEFT),
                    'is_published' => true,
                    'is_available' => true,
                    'track_stock' => $type->key !== BusinessTypeKey::Restaurant->value,
                    'has_modifiers' => $type->key === BusinessTypeKey::Restaurant->value && $index < 2,
                    'prep_time_minutes' => $type->key === BusinessTypeKey::Restaurant->value ? 10 + $index * 5 : null,
                    'attributes' => [],
                ],
            );

            foreach ($variantNames as $variantIndex => $variantName) {
                $variant = ProductVariant::query()->updateOrCreate(
                    ['business_id' => $business->id, 'sku' => strtoupper($type->key).'-'.($index + 1).'-'.($variantIndex + 1)],
                    [
                        'product_id' => $product->id,
                        'name' => ['en' => $variantName, 'ar' => $variantName],
                        'barcode' => '300000'.str_pad((string) ($business->id * 1000 + $index * 10 + $variantIndex), 7, '0', STR_PAD_LEFT),
                        'unit' => ProductUnit::from($unit),
                        'cost_price_cents' => $cost,
                        'wholesale_price_cents' => (int) round($retail * 0.9),
                        'retail_price_cents' => $retail,
                        'stock_qty' => $type->key === BusinessTypeKey::Restaurant->value ? 0 : 50,
                        'low_stock_threshold' => 5,
                        'is_default' => $variantIndex === 0,
                    ],
                );

                if ($type->key === BusinessTypeKey::Jewelry->value) {
                    JewelryAttribute::query()->updateOrCreate(
                        ['product_variant_id' => $variant->id],
                        [
                            'business_id' => $business->id,
                            'pricing_mode' => $variantName === '925' ? JewelryPricingMode::Fixed : JewelryPricingMode::ByWeight,
                            'weight_g' => $variantName === '925' ? null : 5.25 + $variantIndex,
                            'karat' => $variantName === '925' ? null : $variantName,
                            'metal' => $variantName === '925' ? JewelryMetal::Silver->value : JewelryMetal::Gold->value,
                            'making_type' => MakingType::Fixed,
                            'making_value' => 2500,
                        ],
                    );
                }
            }

            if ($type->key === BusinessTypeKey::Restaurant->value && $index < 2) {
                $group = ModifierGroup::query()->updateOrCreate(
                    ['business_id' => $business->id, 'product_id' => $product->id, 'sort_order' => 0],
                    [
                        'name' => ['en' => 'Extras', 'ar' => 'إضافات'],
                        'min_selections' => 0,
                        'max_selections' => 3,
                        'is_required' => false,
                    ],
                );

                foreach ([['Extra sauce', 'صوص إضافي', 100], ['Cheese', 'جبنة', 200], ['Fries', 'بطاطس', 300]] as $modifierIndex => [$modEn, $modAr, $delta]) {
                    Modifier::query()->updateOrCreate(
                        ['business_id' => $business->id, 'modifier_group_id' => $group->id, 'sort_order' => $modifierIndex],
                        [
                            'name' => ['en' => $modEn, 'ar' => $modAr],
                            'price_delta_cents' => $delta,
                            'is_available' => true,
                        ],
                    );
                }
            }
        }
    }

    private function createExpenses(Business $business): void
    {
        $categories = [
            ExpenseCategoryKey::Rent->value => ['en' => 'Rent', 'ar' => 'إيجار'],
            ExpenseCategoryKey::Salaries->value => ['en' => 'Salaries', 'ar' => 'رواتب'],
            ExpenseCategoryKey::Electricity->value => ['en' => 'Electricity', 'ar' => 'كهرباء'],
            ExpenseCategoryKey::Water->value => ['en' => 'Water', 'ar' => 'مياه'],
            ExpenseCategoryKey::Maintenance->value => ['en' => 'Maintenance', 'ar' => 'صيانة'],
            ExpenseCategoryKey::Marketing->value => ['en' => 'Marketing', 'ar' => 'تسويق'],
            ExpenseCategoryKey::Other->value => ['en' => 'Other', 'ar' => 'أخرى'],
        ];

        foreach ($categories as $key => $name) {
            $category = ExpenseCategory::query()->updateOrCreate(
                ['business_id' => $business->id, 'key' => $key],
                ['name' => $name, 'is_system' => true],
            );

            if (in_array($key, [ExpenseCategoryKey::Rent->value, ExpenseCategoryKey::Electricity->value], true)) {
                Expense::query()->updateOrCreate(
                    ['business_id' => $business->id, 'expense_category_id' => $category->id, 'expense_date' => now()->startOfMonth()->toDateString()],
                    [
                        'amount_cents' => $key === ExpenseCategoryKey::Rent->value ? 250000 : 18000,
                        'description' => $name['en'].' — '.now()->format('F Y'),
                        'source' => ExpenseSource::Manual,
                        'is_recurring' => $key === ExpenseCategoryKey::Rent->value,
                        'recurrence' => $key === ExpenseCategoryKey::Rent->value ? ExpenseRecurrence::Monthly->value : null,
                        'next_due_date' => $key === ExpenseCategoryKey::Rent->value ? now()->addMonth()->startOfMonth()->toDateString() : null,
                    ],
                );
            }
        }
    }

    private function createGoldPrices(Business $business): void
    {
        foreach (['18' => 4500, '21' => 5250, '22' => 5500, '24' => 6000] as $karat => $price) {
            GoldPrice::query()->updateOrCreate(
                ['business_id' => $business->id, 'karat' => (string) $karat, 'effective_date' => now()->toDateString()],
                ['price_per_gram_cents' => $price],
            );
        }
    }

    private function createCarShowroom(Business $business): void
    {
        $client = Client::query()->updateOrCreate(
            ['business_id' => $business->id, 'phone' => '+1 555 0200'],
            ['name' => 'John Carter', 'email' => 'john@example.test', 'id_number' => 'TX-99881', 'license_number' => 'DL-77231'],
        );

        $vehicles = [
            ['1HGCM82633A004352', 'Toyota', 'Camry', 2022, 'White', 22000, 28500, VehicleStatus::Available],
            ['1FTFW1E50JFA12345', 'Ford', 'F-150', 2021, 'Black', 31000, 39900, VehicleStatus::Available],
            ['5YJ3E1EA7JF005678', 'Tesla', 'Model 3', 2023, 'Blue', 38000, 46500, VehicleStatus::Sold],
        ];

        foreach ($vehicles as $index => [$vin, $make, $model, $year, $color, $purchase, $listing, $status]) {
            $vehicle = Vehicle::query()->updateOrCreate(
                ['business_id' => $business->id, 'vin' => $vin],
                [
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'color' => $color,
                    'mileage' => 18000 + $index * 9000,
                    'plate_number' => 'TX-'.(1000 + $index),
                    'purchase_price_cents' => $purchase * 100,
                    'listing_price_cents' => $listing * 100,
                    'status' => $status,
                    'purpose' => VehiclePurpose::Sale->value,
                    'buyer_client_id' => $status === VehicleStatus::Sold ? $client->id : null,
                    'sold_price_cents' => $status === VehicleStatus::Sold ? 45500 * 100 : null,
                    'sold_at' => $status === VehicleStatus::Sold ? now()->subDays(5) : null,
                ],
            );

            VehicleCost::query()->updateOrCreate(
                ['business_id' => $business->id, 'vehicle_id' => $vehicle->id, 'description' => 'Detailing & inspection'],
                ['amount_cents' => 45000, 'incurred_at' => now()->subDays(10)],
            );
        }
    }

    private function createCarRental(Business $business): void
    {
        $client = Client::query()->updateOrCreate(
            ['business_id' => $business->id, 'phone' => '+1 555 0300'],
            ['name' => 'Sara Miller', 'email' => 'sara@example.test', 'id_number' => 'TX-55221', 'license_number' => 'DL-66110'],
        );

        $vehicles = [
            ['1G1ZD5ST4LF123456', 'Chevrolet', 'Malibu', 2022, 'Gray', 21000, VehicleStatus::Available],
            ['WBA5R1C55KFH12345', 'BMW', '330i', 2023, 'Silver', 42000, VehicleStatus::Rented],
        ];

        foreach ($vehicles as $index => [$vin, $make, $model, $year, $color, $price, $status]) {
            $vehicle = Vehicle::query()->updateOrCreate(
                ['business_id' => $business->id, 'vin' => $vin],
                [
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'color' => $color,
                    'mileage' => 25000 + $index * 12000,
                    'plate_number' => 'RT-'.(2000 + $index),
                    'purchase_price_cents' => $price * 100,
                    'listing_price_cents' => 0,
                    'status' => $status,
                    'purpose' => VehiclePurpose::Rental->value,
                ],
            );

            if ($status === VehicleStatus::Rented) {
                RentalContract::query()->updateOrCreate(
                    ['business_id' => $business->id, 'contract_number' => 'RC-1001'],
                    [
                        'vehicle_id' => $vehicle->id,
                        'client_id' => $client->id,
                        'starts_at' => now()->subDays(2),
                        'ends_at' => now()->addDays(5),
                        'rate_type' => RentalRateType::Daily->value,
                        'rate_cents' => 12000,
                        'deposit_cents' => 50000,
                        'odometer_out' => 25000,
                        'total_cents' => 84000,
                        'status' => RentalStatus::Active,
                        'paid_at' => now()->subDays(2),
                    ],
                );
            }
        }
    }

    private function createSales(Business $business): void
    {
        $employee = Employee::query()->where('business_id', $business->id)->first();
        $variants = ProductVariant::query()->where('business_id', $business->id)->take(2)->get();

        if ($employee === null || $variants->isEmpty()) {
            return;
        }

        foreach ([1, 2] as $index) {
            $sale = Sale::query()->updateOrCreate(
                ['business_id' => $business->id, 'number' => 'DEMO-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT)],
                [
                    'channel' => SaleChannel::Pos,
                    'employee_id' => $employee->id,
                    'subtotal_cents' => $variants->sum(fn (ProductVariant $variant): int => $variant->retail_price_cents),
                    'total_cents' => $variants->sum(fn (ProductVariant $variant): int => $variant->retail_price_cents),
                    'cogs_cents' => $variants->sum(fn (ProductVariant $variant): int => $variant->cost_price_cents),
                    'status' => SaleStatus::Completed,
                    'sold_at' => now()->subDays($index),
                ],
            );

            foreach ($variants as $variant) {
                SaleItem::query()->updateOrCreate(
                    ['business_id' => $business->id, 'sale_id' => $sale->id, 'product_variant_id' => $variant->id],
                    [
                        'product_id' => $variant->product_id,
                        'name' => $variant->displayName(),
                        'qty' => 1,
                        'unit' => $variant->unit,
                        'unit_price_cents' => $variant->retail_price_cents,
                        'unit_cost_cents' => $variant->cost_price_cents,
                        'total_cents' => $variant->retail_price_cents,
                    ],
                );
            }

            Payment::query()->updateOrCreate(
                ['business_id' => $business->id, 'payable_type' => Sale::class, 'payable_id' => $sale->id],
                [
                    'method' => $index === 1 ? PaymentMethod::Cash : PaymentMethod::Card,
                    'status' => PaymentStatus::Captured,
                    'amount_cents' => $sale->total_cents,
                    'paid_at' => $sale->sold_at,
                ],
            );
        }

        if (in_array($business->businessType->key, [BusinessTypeKey::Restaurant->value, BusinessTypeKey::Grocery->value], true)) {
            $this->createOrder($business, $variants->first());
        }
    }

    private function createOrder(Business $business, ProductVariant $variant): void
    {
        $customer = Customer::query()->updateOrCreate(
            ['email' => 'customer@example.test'],
            ['name' => 'Alex Johnson', 'phone' => '+1 555 0400', 'password' => 'password', 'locale' => 'en'],
        );

        $tax = (int) round($variant->retail_price_cents * 0.15);
        $order = Order::query()->updateOrCreate(
            ['business_id' => $business->id, 'idempotency_key' => 'demo-'.$business->id],
            [
                'customer_id' => $customer->id,
                'number' => 'ORD-'.str_pad((string) $business->id, 4, '0', STR_PAD_LEFT),
                'fulfillment' => FulfillmentType::Pickup,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Authorized,
                'subtotal_cents' => $variant->retail_price_cents,
                'tax_cents' => $tax,
                'total_cents' => $variant->retail_price_cents + $tax,
                'placed_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ],
        );

        OrderItem::query()->updateOrCreate(
            ['business_id' => $business->id, 'order_id' => $order->id, 'product_variant_id' => $variant->id],
            [
                'product_id' => $variant->product_id,
                'name' => $variant->displayName(),
                'qty' => 1,
                'unit' => $variant->unit,
                'unit_price_cents' => $variant->retail_price_cents,
                'unit_cost_cents' => $variant->cost_price_cents,
                'total_cents' => $variant->retail_price_cents,
            ],
        );

        OrderStatusHistory::query()->create([
            'business_id' => $business->id,
            'order_id' => $order->id,
            'status' => OrderStatus::Pending,
            'note' => 'Order placed',
            'changed_by_type' => Customer::class,
            'changed_by_id' => $customer->id,
        ]);
    }

    /** Email domains can't contain underscores, so "car_showroom" becomes "car-showroom.test". */
    private function domain(BusinessType $type): string
    {
        return str_replace('_', '-', $type->key).'.test';
    }
}
