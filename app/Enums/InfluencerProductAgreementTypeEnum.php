<?php

namespace App\Enums;

enum InfluencerProductAgreementTypeEnum: string
{
    case LOAN = 'loan';   // Do kthehet
    case GIFT = 'gift';   // Nuk kthehet (dhurate)
    case TBD  = 'tbd';    // Do vendoset me vone

    public static function default(): self
    {
        return self::LOAN;
    }

    public static function values(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match($this) {
            self::LOAN => __('influencer_product.agreement.loan'),
            self::GIFT => __('influencer_product.agreement.gift'),
            self::TBD  => __('influencer_product.agreement.tbd'),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOAN => 'info',
            self::GIFT => 'success',
            self::TBD  => 'warning',
        };
    }
}
