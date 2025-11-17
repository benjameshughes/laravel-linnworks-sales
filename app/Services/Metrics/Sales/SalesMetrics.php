<?php

declare(strict_types=1);

namespace App\Services\Metrics\Sales;

use App\Factories\Metrics\Sales\SalesFactory;
use App\Repositories\Metrics\Sales\SalesRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final readonly class SalesMetrics
{

    public function __construct(private SalesRepository $salesRepo)
    {}

    public function growthRate(int $days): float
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->copy()->subDays($days);

        // Current period
        $currentOrders = $this->salesRepo->getOrdersBetweenDates($startDate, $endDate);

        // Pervious period
        $previousStartDate = $startDate->copy()->subDays($days);
        $previousEndDate = $endDate->copy()->subDays($days);
        $previousOrders = $this->salesRepo->getOrdersBetweenDates($previousStartDate, $previousEndDate);

        // Create factories and compare
        $currentFactory = new SalesFactory($currentOrders);
        $previousFactory = new SalesFactory($previousOrders);

        return $currentFactory->growthRate($previousFactory);
    }

}