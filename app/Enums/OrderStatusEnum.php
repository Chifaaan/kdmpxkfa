<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case CREATED = 'new';
    case PROCESS = 'diproses';
    case DELIVERY = 'dalam-pengiriman';
    case RECEIVED = 'diterima';
    case PENDING = 'pending';
    case CANCELED = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Dibuat',
            self::PROCESS => 'Diproses',
            self::DELIVERY => 'Dalam Pengiriman',
            self::RECEIVED => 'Diterima',
            self::PENDING => 'Pending',
            self::CANCELED => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'text-blue-600 bg-blue-100 dark:text-blue-200 dark:bg-blue-900/40',
            self::PROCESS => 'text-amber-600 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/40',
            self::DELIVERY => 'text-sky-600 bg-sky-100 dark:text-sky-200 dark:bg-sky-900/40',
            self::RECEIVED => 'text-emerald-600 bg-emerald-100 dark:text-emerald-200 dark:bg-emerald-900/40',
            self::CANCELED => 'text-rose-600 bg-rose-100 dark:text-rose-200 dark:bg-rose-900/40',
        };
    }

    public static function colors(): array
    {
        return [
            self::CREATED->value => 'text-blue-600 bg-blue-100 dark:text-blue-200 dark:bg-blue-900/40',
            self::PROCESS->value => 'text-amber-600 bg-amber-100 dark:text-amber-200 dark:bg-amber-900/40',
            self::DELIVERY->value => 'text-sky-600 bg-sky-100 dark:text-sky-200 dark:bg-sky-900/40',
            self::RECEIVED->value => 'text-emerald-600 bg-emerald-100 dark:text-emerald-200 dark:bg-emerald-900/40',
            self::CANCELED->value => 'text-rose-600 bg-rose-100 dark:text-rose-200 dark:bg-rose-900/40',
        ];
    }

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }

        return $array;
    }
}
