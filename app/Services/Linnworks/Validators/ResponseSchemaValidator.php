<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Validators;

use App\ValueObjects\Linnworks\Validation\ValidationResult;

/**
 * Validates Linnworks API response schemas
 */
class ResponseSchemaValidator
{
    /**
     * Validate OpenOrders response structure
     */
    public function validateOpenOrdersResponse(array $response): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Check for required top-level keys
        if (! isset($response['Data']) && ! is_array($response)) {
            $errors['structure'][] = 'Response must contain "Data" key or be an array of orders';
        }

        // Get orders array
        $orders = $response['Data'] ?? $response;

        if (! is_array($orders)) {
            $errors['structure'][] = 'Orders data must be an array';

            return ValidationResult::failed($errors);
        }

        // Validate individual orders
        foreach ($orders as $index => $order) {
            $orderErrors = $this->validateSingleOrderStructure($order, 'open');
            if (! empty($orderErrors)) {
                foreach ($orderErrors as $field => $fieldErrors) {
                    $errors["order.{$index}.{$field}"] = $fieldErrors;
                }
            }
        }

        // Check metadata
        if (isset($response['TotalPages']) && ! is_numeric($response['TotalPages'])) {
            $warnings['metadata'][] = 'TotalPages should be numeric';
        }

        return empty($errors)
            ? (empty($warnings) ? ValidationResult::success() : ValidationResult::withWarnings($warnings))
            : ValidationResult::failed($errors, $warnings);
    }

    /**
     * Validate ProcessedOrders response structure
     */
    public function validateProcessedOrdersResponse(array $response): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Processed orders have different structure
        if (! isset($response['Data']) && ! is_array($response)) {
            $errors['structure'][] = 'Response must contain "Data" key or be an array of orders';
        }

        $orders = $response['Data'] ?? $response;

        if (! is_array($orders)) {
            $errors['structure'][] = 'Orders data must be an array';

            return ValidationResult::failed($errors);
        }

        foreach ($orders as $index => $order) {
            $orderErrors = $this->validateSingleOrderStructure($order, 'processed');
            if (! empty($orderErrors)) {
                foreach ($orderErrors as $field => $fieldErrors) {
                    $errors["order.{$index}.{$field}"] = $fieldErrors;
                }
            }
        }

        return empty($errors)
            ? (empty($warnings) ? ValidationResult::success() : ValidationResult::withWarnings($warnings))
            : ValidationResult::failed($errors, $warnings);
    }

    /**
     * Validate GetOpenOrdersById response
     */
    public function validateOrderDetailsResponse(array $response): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Can be single order or array of orders
        $orders = [];

        if (isset($response['Orders'])) {
            $orders = $response['Orders'];
        } elseif (isset($response['Data'])) {
            $orders = $response['Data'];
        } elseif (isset($response['GeneralInfo'])) {
            // Single order
            $orders = [$response];
        } else {
            $errors['structure'][] = 'Unable to determine order data structure';

            return ValidationResult::failed($errors);
        }

        if (! is_array($orders)) {
            $errors['structure'][] = 'Orders must be an array';

            return ValidationResult::failed($errors);
        }

        foreach ($orders as $index => $order) {
            // Detailed orders should have GeneralInfo, TotalsInfo, Items
            if (! isset($order['GeneralInfo'])) {
                $errors["order.{$index}.GeneralInfo"][] = 'Missing required GeneralInfo section';
            }

            if (! isset($order['Items'])) {
                $warnings["order.{$index}.Items"][] = 'Order has no items';
            }

            if (! isset($order['TotalsInfo'])) {
                $warnings["order.{$index}.TotalsInfo"][] = 'Missing TotalsInfo section';
            }
        }

        return empty($errors)
            ? (empty($warnings) ? ValidationResult::success() : ValidationResult::withWarnings($warnings))
            : ValidationResult::failed($errors, $warnings);
    }

    /**
     * Validate single order structure based on type
     */
    private function validateSingleOrderStructure(mixed $order, string $type): array
    {
        $errors = [];

        if (! is_array($order)) {
            $errors['type'][] = 'Order must be an array';

            return $errors;
        }

        // Check for order identifier
        $hasOrderId = isset($order['OrderId']) || isset($order['pkOrderID']);
        $hasOrderNumber = isset($order['OrderNumber']) || isset($order['nOrderId']);

        if (! $hasOrderId && ! $hasOrderNumber) {
            $errors['identifier'][] = 'Order must have OrderId or OrderNumber';
        }

        // Type-specific validation
        if ($type === 'open') {
            $this->validateOpenOrderFields($order, $errors);
        } elseif ($type === 'processed') {
            $this->validateProcessedOrderFields($order, $errors);
        }

        return $errors;
    }

    /**
     * Validate open order specific fields
     */
    private function validateOpenOrderFields(array $order, array &$errors): void
    {
        // Open orders should have certain fields
        $recommendedFields = ['Source', 'ReceivedDate', 'SubSource'];

        foreach ($recommendedFields as $field) {
            if (! isset($order[$field]) && ! isset($order[lcfirst($field)])) {
                // This is a warning-level issue, not an error
                // We'll just skip for now as it's not critical
            }
        }

        // Validate totals if present
        if (isset($order['TotalCharge']) && ! is_numeric($order['TotalCharge'])) {
            $errors['TotalCharge'][] = 'TotalCharge must be numeric';
        }
    }

    /**
     * Validate processed order specific fields
     */
    private function validateProcessedOrderFields(array $order, array &$errors): void
    {
        // Processed orders should have processing timestamps
        if (isset($order['ProcessedDateTime'])) {
            if (! is_string($order['ProcessedDateTime']) && ! ($order['ProcessedDateTime'] instanceof \DateTimeInterface)) {
                $errors['ProcessedDateTime'][] = 'ProcessedDateTime must be a valid date string';
            }
        }
    }

    /**
     * Validate view stats response
     */
    public function validateViewStatsResponse(array $response): ValidationResult
    {
        $errors = [];

        if (! is_array($response) || empty($response)) {
            $errors['structure'][] = 'ViewStats response must be a non-empty array';

            return ValidationResult::failed($errors);
        }

        // Each item should have ViewId and TotalOrders
        foreach ($response as $index => $stat) {
            if (! isset($stat['ViewId'])) {
                $errors["stat.{$index}.ViewId"][] = 'ViewId is required';
            }

            if (! isset($stat['TotalOrders']) || ! is_numeric($stat['TotalOrders'])) {
                $errors["stat.{$index}.TotalOrders"][] = 'TotalOrders must be numeric';
            }
        }

        return empty($errors)
            ? ValidationResult::success()
            : ValidationResult::failed($errors);
    }

    /**
     * Validate order IDs response
     */
    public function validateOrderIdsResponse(array $response): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Should have Data or Results
        $ids = $response['Data'] ?? $response['Results'] ?? null;

        if ($ids === null) {
            $errors['structure'][] = 'Response must contain "Data" or "Results"';

            return ValidationResult::failed($errors);
        }

        if (! is_array($ids)) {
            $errors['structure'][] = 'Order IDs must be an array';

            return ValidationResult::failed($errors);
        }

        // Check for pagination metadata
        if (! isset($response['TotalEntries'])) {
            $warnings['pagination'][] = 'Missing TotalEntries in response';
        }

        if (! isset($response['TotalPages'])) {
            $warnings['pagination'][] = 'Missing TotalPages in response';
        }

        return empty($errors)
            ? (empty($warnings) ? ValidationResult::success() : ValidationResult::withWarnings($warnings))
            : ValidationResult::failed($errors, $warnings);
    }

    /**
     * Validate any Linnworks API response for common issues
     */
    public function validateGenericResponse(array $response): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Check for API error indicators
        if (isset($response['Error']) && ! empty($response['Error'])) {
            $errors['api_error'][] = $response['Error'];
        }

        if (isset($response['ErrorMessage']) && ! empty($response['ErrorMessage'])) {
            $errors['api_error'][] = $response['ErrorMessage'];
        }

        // Check for empty response
        if (empty($response)) {
            $warnings['empty'][] = 'Response is empty';
        }

        return empty($errors)
            ? (empty($warnings) ? ValidationResult::success() : ValidationResult::withWarnings($warnings))
            : ValidationResult::failed($errors, $warnings);
    }
}
