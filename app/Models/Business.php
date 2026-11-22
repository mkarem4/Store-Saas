<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Enums\BusinessStatus;
use App\Models\Concerns\HasTranslations;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Business extends Model implements HasMedia, HasName
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    public const DEFAULT_SETTINGS = [
        'pickup_enabled' => true,
        'delivery_enabled' => false,
        'delivery_fee_per_km_cents' => 0,
        'delivery_base_fee_cents' => 0,
        'delivery_max_km' => 10,
        'minimum_order_cents' => 0,
        'accept_timeout_minutes' => 10,
        'orders_paused' => false,
        'allow_negative_stock' => false,
        'working_hours' => [
            'mon' => ['open' => '09:00', 'close' => '21:00'],
            'tue' => ['open' => '09:00', 'close' => '21:00'],
            'wed' => ['open' => '09:00', 'close' => '21:00'],
            'thu' => ['open' => '09:00', 'close' => '21:00'],
            'fri' => ['open' => '09:00', 'close' => '21:00'],
            'sat' => ['open' => '10:00', 'close' => '20:00'],
            'sun' => null,
        ],
    ];

    protected $fillable = [
        'business_type_id',
        'name',
        'slug',
        'description',
        'phone',
        'email',
        'website',
        'address_line',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'currency',
        'currency_decimals',
        'timezone',
        'status',
        'allowed_ips',
        'attendance_mode',
        'geofence_radius_m',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'status' => BusinessStatus::class,
            'allowed_ips' => 'array',
            'attendance_mode' => AttendanceMode::class,
            'geofence_radius_m' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            if (blank($business->slug)) {
                $business->slug = Str::slug($business->translated('name', 'en')).'-'.Str::lower(Str::random(5));
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'business_owner');
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function settings(string $key, mixed $default = null): mixed
    {
        $settings = array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);

        return data_get($settings, $key, $default);
    }

    public function updateSettings(array $values): void
    {
        $this->settings = array_merge($this->settings ?? [], $values);
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(BusinessWallet::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * Sales tax percentage for the store's region (D31): state rate, then the
     * country rate, then the platform default (15% until the client's file arrives).
     */
    public function taxRate(): float
    {
        return TaxRate::lookup($this->country, $this->state)
            ?? (float) PlatformSetting::get('default_tax_rate', PlatformSetting::get('tax_rate', 15));
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function acceptsOrders(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->settings('orders_paused', false)) {
            return false;
        }

        return (bool) ($this->settings('pickup_enabled') || $this->settings('delivery_enabled'));
    }

    public function isOpenNow(): bool
    {
        $now = now($this->timezone);
        $day = strtolower($now->format('D'));
        $hours = $this->settings("working_hours.{$day}");

        if (blank($hours) || empty($hours['open']) || empty($hours['close'])) {
            return false;
        }

        $open = $now->copy()->setTimeFromTimeString($hours['open']);
        $close = $now->copy()->setTimeFromTimeString($hours['close']);

        if ($close->lessThanOrEqualTo($open)) {
            return $now->greaterThanOrEqualTo($open) || $now->lessThanOrEqualTo($close);
        }

        return $now->betweenIncluded($open, $close);
    }

    public function translatedName(): string
    {
        return $this->translated('name');
    }

    public function getFilamentName(): string
    {
        return $this->translatedName();
    }
}
