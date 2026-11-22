<?php

namespace App\Services;

use App\Enums\ExpenseSource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Expenses the system books on the store's behalf (M5.5): payment fees and
 * platform commission. One row per (reference, source) so retries are safe.
 */
class SystemExpenseRecorder
{
    public const CATEGORIES = [
        'payment_fees' => ['en' => 'Payment fees', 'ar' => 'رسوم الدفع'],
        'platform_commission' => ['en' => 'Platform commission', 'ar' => 'عمولة المنصة'],
    ];

    public function record(
        int $businessId,
        string $categoryKey,
        ExpenseSource $source,
        int $amountCents,
        string $description,
        Model $reference,
        ?Carbon $date = null,
    ): ?Expense {
        $category = ExpenseCategory::query()
            ->withoutGlobalScope('business')
            ->firstOrCreate(
                ['business_id' => $businessId, 'key' => $categoryKey],
                ['name' => self::CATEGORIES[$categoryKey] ?? ['en' => $categoryKey, 'ar' => $categoryKey], 'is_system' => true],
            );

        $attributes = [
            'business_id' => $businessId,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'source' => $source,
        ];

        if ($amountCents <= 0) {
            Expense::query()->withoutGlobalScope('business')->where($attributes)->delete();

            return null;
        }

        return Expense::query()->withoutGlobalScope('business')->updateOrCreate($attributes, [
            'expense_category_id' => $category->id,
            'amount_cents' => $amountCents,
            'description' => $description,
            'expense_date' => ($date ?? now())->toDateString(),
            'is_recurring' => false,
        ]);
    }
}
