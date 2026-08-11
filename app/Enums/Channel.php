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

    public function label(): string
    {
        return match ($this) {
            self::Amazon => 'Amazon',
            self::AmazonFba => 'Amazon FBA',
            self::Ebay => 'eBay',
            self::Shopify => 'Website',
            self::WooCommerce => 'WOOCOMMERCE',
            self::Etsy => 'ETSY',
            self::MiraklMp => 'Mirakl MP',
            self::VirtualStock => 'Wilko',
            self::TheRange => 'The Range',
            self::Tesco => 'TESCO',
            self::TemuEu => 'TEMU',
            self::OnBuy => 'OnBuy',
            self::Direct => 'DIRECT',
            self::DataImportExport => 'DATAIMPORTEXPORT',
        };
    }
}
