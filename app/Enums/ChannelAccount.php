<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelAccount: string
{
    case BlindsOutlet = 'Blinds Outlet';
    case FiftyfiveCausewayRoad = '55CausewayRoad';
    case TheBlindsOutlet = 'theblindsoutlet';
    case Ebay1 = 'EBAY1';
    case BlindsCurtainMegastore = 'blindscurtainmegastore';
    case CorbieHomeProducts = 'corbiehomeproducts';
    case Range = 'range';
    case TheRange = 'therange';
    case Wilko = 'Wilko';
    case BandQ = 'BANDQ';
    case Debenhams = 'Debenhams';
    case Freemans = 'Freemans';
    case Tesco = 'Tesco';
    case BlindsOutletShopify = 'BlindsOutlet';
    case TheBlindsOutletTemu = 'The Blinds Outlet';
    case OnBuy = 'onbuy';
    case CaecusBlindsCo = 'https://caecusblinds.co.uk';
    case BlindsOutletCo = 'https://www.blindsoutlet.co.uk';
    case CaecusBlindsEtsy = 'CaecusBlinds';
    case Rma = 'RMA';
    case Ebay0 = 'EBAY0';

    public static function displayName(?string $raw, ?string $source = null): string
    {
        $account = self::tryFrom($raw ?? '');

        if (! $account) {
            return $raw ?? 'Unknown';
        }

        return $account->labelForSource($source);
    }

    public function labelForSource(?string $source): string
    {
        return match (true) {
            $this === self::BlindsOutlet && $source === 'Mirakl MP' => 'B&Q',
            default => $this->label(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::BlindsOutlet => 'Blinds Outlet',
            self::FiftyfiveCausewayRoad => '55 Causeway Road',
            self::TheBlindsOutlet => 'The Blinds Outlet',
            self::Ebay1 => '55 Causeway Road',
            self::BlindsCurtainMegastore => 'Blinds Curtain Megastore',
            self::CorbieHomeProducts => 'Corbie Home Products',
            self::Range => 'The Range',
            self::TheRange => 'The Range',
            self::Wilko => 'Wilko',
            self::BandQ => 'B&Q',
            self::Debenhams => 'Debenhams',
            self::Freemans => 'Freemans',
            self::Tesco => 'Tesco',
            self::BlindsOutletShopify => 'Blinds Outlet',
            self::TheBlindsOutletTemu => 'The Blinds Outlet',
            self::OnBuy => 'OnBuy',
            self::CaecusBlindsCo => 'Caecus Blinds',
            self::BlindsOutletCo => 'Blinds Outlet',
            self::CaecusBlindsEtsy => 'Caecus Blinds',
            self::Rma => 'RMA',
            self::Ebay0 => 'eBay Store 0',
        };
    }
}
