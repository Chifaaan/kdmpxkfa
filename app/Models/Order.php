<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'transaction_number',
        'user_id',
        'tenant_id',
        'status',
        'source_of_fund',
        'account_no',
        'account_bank',
        'payment_type',
        'payment_method',
        'va_number',
        'subtotal',
        'subtotal_delivered',
        'tax_amount',
        'tax_delivered',
        'shipping_amount',
        'discount_amount',
        'total_price',
        'total_delivered',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'shipping_name',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_method',
        'tracking_number',
        'estimated_delivery',
        'shipped_at',
        'delivered_at',
        'customer_notes',
        'admin_notes',
        'snap_token',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        // 'total_price' => 'decimal:2', //Decimal is not supported with .toLocale() function
        'estimated_delivery' => 'date',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apotek()
    {
        return $this->user()->first()?->apotek ?? null;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot('quantity', 'qty_delivered')
            ->withTimestamps();
    }

    public static function generateTransactionNumber(): string
    {
        $prefix = 'TKF';
        $apotekCode = auth()->user()->apotek->sap_id;
        $date = now()->format('ymd');
        $random = substr(uniqid(), -8);

        return "{$prefix}{$apotekCode}{$date}{$random}";
    }

    // Accessor for billing full name (assuming it's the same as billing_name)
    public function getBillingFullNameAttribute(): string
    {
        return $this->billing_name;
    }

    // Accessor for shipping full name (assuming it's the same as shipping_name)
    public function getShippingFullNameAttribute(): string
    {
        return $this->shipping_name ?? $this->billing_name;
    }
}
