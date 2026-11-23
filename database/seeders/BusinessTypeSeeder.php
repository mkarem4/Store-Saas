<?php

namespace Database\Seeders;

use App\Enums\BusinessTypeKey;
use App\Enums\JewelryMetal;
use App\Enums\JewelryPricingMode;
use App\Enums\ProductUnit;
use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'key' => BusinessTypeKey::CarShowroom->value,
                'name' => ['en' => 'Car Showroom', 'ar' => 'معرض سيارات'],
                'icon' => 'heroicon-o-truck',
                'fields' => [
                    $this->field('vin', 'VIN', 'رقم الشاسي', 'text', true),
                    $this->field('make', 'Make', 'الماركة', 'text', true),
                    $this->field('model', 'Model', 'الموديل', 'text', true),
                    $this->field('year', 'Year', 'السنة', 'number', true),
                    $this->field('color', 'Color', 'اللون', 'text'),
                    $this->field('mileage', 'Mileage', 'الممشى', 'number'),
                    $this->field('plate_number', 'Plate number', 'رقم اللوحة', 'text'),
                    $this->field('purchase_price', 'Purchase price', 'سعر الشراء', 'number', true),
                    $this->field('listing_price', 'Listing price', 'سعر العرض', 'number', true),
                    $this->field('sold_price', 'Sold price', 'سعر البيع', 'number'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Electronics->value,
                'name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات'],
                'icon' => 'heroicon-o-device-phone-mobile',
                'fields' => [
                    $this->field('brand', 'Brand', 'الماركة', 'text'),
                    $this->field('model', 'Model', 'الموديل', 'text'),
                    $this->field('serial_tracking', 'Track IMEI/Serial', 'تتبع السيريال', 'boolean'),
                ],
            ],
            [
                'key' => BusinessTypeKey::CarRental->value,
                'name' => ['en' => 'Car Rental', 'ar' => 'تأجير سيارات'],
                'icon' => 'heroicon-o-key',
                'fields' => [
                    $this->field('vin', 'VIN', 'رقم الشاسي', 'text'),
                    $this->field('plate_number', 'Plate number', 'رقم اللوحة', 'text'),
                    $this->field('daily_rate', 'Daily rate', 'السعر اليومي', 'number'),
                    $this->field('weekly_rate', 'Weekly rate', 'السعر الأسبوعي', 'number'),
                    $this->field('monthly_rate', 'Monthly rate', 'السعر الشهري', 'number'),
                    $this->field('deposit', 'Deposit', 'التأمين', 'number'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Jewelry->value,
                'name' => ['en' => 'Jewelry', 'ar' => 'مجوهرات'],
                'icon' => 'heroicon-o-sparkles',
                'fields' => [
                    $this->field('pricing_mode', 'Pricing mode', 'طريقة التسعير', 'select', true, [JewelryPricingMode::Fixed->value, JewelryPricingMode::ByWeight->value]),
                    $this->field('weight_g', 'Weight (g)', 'الوزن (جرام)', 'number'),
                    $this->field('karat', 'Karat', 'العيار', 'select', false, ['18', '21', '22', '24']),
                    $this->field('metal', 'Metal', 'المعدن', 'select', false, [JewelryMetal::Gold->value, JewelryMetal::Silver->value, JewelryMetal::Platinum->value]),
                    $this->field('making_value', 'Making charge', 'المصنعية', 'number'),
                    $this->field('certificate_number', 'Certificate number', 'رقم الشهادة', 'text'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Perfume->value,
                'name' => ['en' => 'Perfume', 'ar' => 'عطور'],
                'icon' => 'heroicon-o-beaker',
                'fields' => [
                    $this->field('size_ml', 'Size (ml)', 'الحجم (مل)', 'number'),
                    $this->field('concentration', 'Concentration', 'التركيز', 'select', false, ['EDP', 'EDT', 'Parfum', 'Cologne']),
                    $this->field('brand', 'Brand', 'الماركة', 'text'),
                    $this->field('barcode', 'Barcode', 'الباركود', 'text'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Clothing->value,
                'name' => ['en' => 'Clothing', 'ar' => 'ملابس'],
                'icon' => 'heroicon-o-shopping-bag',
                'fields' => [
                    $this->field('size', 'Size', 'المقاس', 'text'),
                    $this->field('color', 'Color', 'اللون', 'text'),
                    $this->field('material', 'Material', 'الخامة', 'text'),
                    $this->field('brand', 'Brand', 'الماركة', 'text'),
                    $this->field('sku', 'SKU', 'رمز المنتج', 'text'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Grocery->value,
                'name' => ['en' => 'Grocery', 'ar' => 'بقالة'],
                'icon' => 'heroicon-o-shopping-cart',
                'fields' => [
                    $this->field('unit', 'Unit', 'الوحدة', 'select', true, [ProductUnit::Piece->value, ProductUnit::Carton->value, ProductUnit::Kilogram->value]),
                    $this->field('barcode', 'Barcode', 'الباركود', 'text'),
                    $this->field('expiry_date', 'Expiry date', 'تاريخ الصلاحية', 'date'),
                    $this->field('low_stock_threshold', 'Low stock threshold', 'حد المخزون المنخفض', 'number'),
                ],
            ],
            [
                'key' => BusinessTypeKey::Restaurant->value,
                'name' => ['en' => 'Restaurant', 'ar' => 'مطعم'],
                'icon' => 'heroicon-o-cake',
                'fields' => [
                    $this->field('prep_time_minutes', 'Preparation time (minutes)', 'وقت التحضير (دقائق)', 'number'),
                    $this->field('is_available', 'Available', 'متاح', 'boolean'),
                    $this->field('modifiers', 'Has modifiers', 'إضافات', 'boolean'),
                ],
            ],
        ];

        foreach ($types as $index => $type) {
            BusinessType::query()->updateOrCreate(
                ['key' => $type['key']],
                [...$type, 'sort_order' => $index, 'is_active' => true],
            );
        }
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function field(
        string $key,
        string $labelEn,
        string $labelAr,
        string $type,
        bool $required = false,
        ?array $options = null,
    ): array {
        return [
            'key' => $key,
            'label' => ['en' => $labelEn, 'ar' => $labelAr],
            'type' => $type,
            'required' => $required,
            'options' => $options,
        ];
    }
}
