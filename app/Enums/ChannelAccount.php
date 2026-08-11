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

    public static function displayName(?string $raw): string
    {
        return self::tryFrom($raw ?? '')?->label() ?? $raw ?? 'Unknown';
    }

    public function label(): string
    {
        return match ($this) {
            self::BlindsOutlet => 'Blinds Outlet',
            self::FiftyfiveCausewayRoad => '55CausewayRoad',
            self::TheBlindsOutlet => 'theblindsoutlet',
            self::Ebay1 => 'EBAY1',
            self::BlindsCurtainMegastore => 'blindscurtainmegastore',
            self::CorbieHomeProducts => 'corbiehomeproducts',
            self::Range => 'range',
            self::TheRange => 'therange',
            self::Wilko => 'Wilko',
            self::BandQ => 'BANDQ',
            self::Debenhams => 'Debenhams',
            self::Freemans => 'Freemans',
            self::Tesco => 'Tesco',
            self::BlindsOutletShopify => 'BlindsOutlet',
            self::TheBlindsOutletTemu => 'The Blinds Outlet',
            self::OnBuy => 'onbuy',
            self::CaecusBlindsCo => 'https://caecusblinds.co.uk',
            self::BlindsOutletCo => 'https://www.blindsoutlet.co.uk',
            self::CaecusBlindsEtsy => 'CaecusBlinds',
            self::Rma => 'RMA',
            self::Ebay0 => 'EBAY0',
        };
    }
}
