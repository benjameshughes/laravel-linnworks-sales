<?php

declare(strict_types=1);

namespace App\Enums;

enum Channel: string
{
    case Amazon = 'AMAZON';
    case AmazonFba = 'AMAZON FBA';
    case Ebay = 'EBAY';
    case Shopify = 'SHOPIFY';
    case WooCommerce = 'WOOCOMMERCE';
    case Etsy = 'ETSY';
    case MiraklMp = 'Mirakl MP';
    case VirtualStock = 'VIRTUALSTOCK';
    case TheRange = 'The Range';
    case Tesco = 'TESCO';
    case TemuEu = 'TEMU EU';
    case OnBuy = 'OnBuy.com';
    case Direct = 'DIRECT';
    case DataImportExport = 'DATAIMPORTEXPORT';

    public static function displayName(?string $raw): string
    {
        return self::tryFrom($raw ?? '')?->label() ?? $raw ?? 'Unknown';
    }

    public function feePercentage(): float
    {
        return (float) config("channel-fees.{$this->value}", 0.0);
    }

    public static function feePercentageFor(?string $source): float
    {
        return self::tryFrom($source ?? '')?->feePercentage() ?? 0.0;
    }

    public function label(): string
    {
        return match ($this) {
            self::Amazon => 'Amazon',
            self::AmazonFba => 'Amazon FBA',
            self::Ebay => 'eBay',
            self::Shopify => 'Website',
            self::WooCommerce => 'WooCommerce',
            self::Etsy => 'Etsy',
            self::MiraklMp => 'Mirakl',
            self::VirtualStock => 'Wilko',
            self::TheRange => 'The Range',
            self::Tesco => 'Tesco',
            self::TemuEu => 'TEMU',
            self::OnBuy => 'OnBuy',
            self::Direct => 'Direct',
            self::DataImportExport => 'Imported',
        };
    }
}
