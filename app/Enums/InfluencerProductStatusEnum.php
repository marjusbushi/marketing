<?php

namespace App\Enums;

enum InfluencerProductStatusEnum: string
{
    case DRAFT              = 'draft';              // Krijuar, pret aktivizim
    case ACTIVE             = 'active';             // Produktet jane dhene, ne Marketing WH
    case PARTIALLY_RETURNED = 'partially_returned'; // Disa produkte u kthyen
    case RETURNED           = 'returned';           // Te gjitha produktet u kthyen
    case CONVERTED          = 'converted';          // U konvertua ne shitje/expense
    case CANCELLED          = 'cancelled';          // U anullua

    public static function default(): self
    {
        return self::DRAFT;
    }

    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match($this) {
            self::DRAFT              => __('influencer_product.status.draft'),
            self::ACTIVE             => __('influencer_product.status.active'),
            self::PARTIALLY_RETURNED => __('influencer_product.status.partially_returned'),
            self::RETURNED           => __('influencer_product.status.returned'),
            self::CONVERTED          => __('influencer_product.status.converted'),
            self::CANCELLED          => __('influencer_product.status.cancelled'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT              => 'warning',   // Yellow - pret aktivizim
            self::ACTIVE             => 'info',      // Blue - aktiv
            self::PARTIALLY_RETURNED => 'purple',    // Purple - kthim i pjesshem
            self::RETURNED           => 'success',   // Green - u kthye
            self::CONVERTED          => 'primary',   // Primary - u konvertua
            self::CANCELLED          => 'danger',    // Red - u anullua
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DRAFT              => 'heroicons:document',
            self::ACTIVE             => 'heroicons:arrow-right-circle',
            self::PARTIALLY_RETURNED => 'heroicons:arrow-uturn-left',
            self::RETURNED           => 'heroicons:check-circle',
            self::CONVERTED          => 'heroicons:shopping-cart',
            self::CANCELLED          => 'heroicons:x-circle',
        };
    }

    /**
     * A mund te anulohet?
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE]);
    }

    /**
     * A eshte aktiv (produktet jane jashte)?
     */
    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::PARTIALLY_RETURNED]);
    }

    /**
     * A eshte perfunduar?
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::RETURNED, self::CONVERTED, self::CANCELLED]);
    }

    /**
     * A mund te regjistrohet kthim?
     */
    public function canRegisterReturn(): bool
    {
        return in_array($this, [self::ACTIVE, self::PARTIALLY_RETURNED]);
    }

    /**
     * A mund te konvertohet ne shitje?
     */
    public function canConvert(): bool
    {
        return in_array($this, [self::ACTIVE, self::PARTIALLY_RETURNED]);
    }
}
