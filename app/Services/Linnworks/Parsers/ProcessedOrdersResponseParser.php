<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Parsers;

use App\ValueObjects\Linnworks\ApiResponse;
use Illuminate\Support\Collection;

/**
 * Parser for Linnworks ProcessedOrders API responses
 *
 * The ProcessedOrders/SearchProcessedOrders endpoint returns a nested structure:
 * {
 *   "ProcessedOrders": {
 *     "Data": [...orders...],
 *     "TotalEntries": 8208,
 *     "TotalPages": 42,
 *     "PageNumber": 1,
 *     "EntriesPerPage": 200
 *   }
 * }
 *
 * This class handles parsing that structure and extracting the relevant data.
 */
final readonly class ProcessedOrdersResponseParser
{
    /**
     * Parse orders from API response
     */
    public function parseOrders(ApiResponse $response): Collection
    {
        if ($response->isError()) {
            return collect();
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return collect($processedOrders['Data'] ?? []);
    }

    /**
     * Get total number of orders available
     */
    public function getTotalEntries(ApiResponse $response): int
    {
        if ($response->isError()) {
            return 0;
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return $processedOrders['TotalEntries'] ?? 0;
    }

    /**
     * Get total number of pages available
     */
    public function getTotalPages(ApiResponse $response): int
    {
        if ($response->isError()) {
            return 0;
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return $processedOrders['TotalPages'] ?? 0;
    }

    /**
     * Get current page number
     */
    public function getPageNumber(ApiResponse $response): int
    {
        if ($response->isError()) {
            return 0;
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return $processedOrders['PageNumber'] ?? 0;
    }

    /**
     * Get entries per page setting
     */
    public function getEntriesPerPage(ApiResponse $response): int
    {
        if ($response->isError()) {
            return 0;
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return $processedOrders['EntriesPerPage'] ?? 0;
    }

    /**
     * Parse complete pagination metadata
     */
    public function parsePaginationMetadata(ApiResponse $response): array
    {
        if ($response->isError()) {
            return [
                'total_entries' => 0,
                'total_pages' => 0,
                'page_number' => 0,
                'entries_per_page' => 0,
            ];
        }

        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return [
            'total_entries' => $processedOrders['TotalEntries'] ?? 0,
            'total_pages' => $processedOrders['TotalPages'] ?? 0,
            'page_number' => $processedOrders['PageNumber'] ?? 0,
            'entries_per_page' => $processedOrders['EntriesPerPage'] ?? 0,
        ];
    }
}
