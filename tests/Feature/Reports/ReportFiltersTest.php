<?php

use App\Reports\Filters\AbstractFilter;
use App\Reports\Filters\DateRangeFilter;
use App\Reports\Filters\NumberRangeFilter;
use App\Reports\Filters\SkuFilter;
use App\Reports\Filters\StatusFilter;
use App\Reports\Filters\SubsourceFilter;
use App\Reports\Filters\TextFilter;
use App\Reports\ProductPerformanceReport;

test('date range filter extends abstract filter', function () {
    $filter = new DateRangeFilter;

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('date_range');
    expect($filter->type())->toBe('date_range');
    expect($filter->label())->toBe('Date Range');
});

test('sku filter extends abstract filter and has dynamic options', function () {
    $filter = new SkuFilter;

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('skus');
    expect($filter->type())->toBe('multi_select');
    expect($filter->hasDynamicOptions())->toBeTrue();
    expect($filter->icon())->toBe('cube');
});

test('subsource filter extends abstract filter and has dynamic options', function () {
    $filter = new SubsourceFilter;

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('subsources');
    expect($filter->type())->toBe('multi_select');
    expect($filter->hasDynamicOptions())->toBeTrue();
    expect($filter->icon())->toBe('rectangle-group');
});

test('status filter has static options', function () {
    $filter = new StatusFilter;

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('statuses');
    expect($filter->type())->toBe('multi_select');
    expect($filter->options())->toBe(['processed', 'open', 'cancelled']);
    expect($filter->hasDynamicOptions())->toBeFalse();
    expect($filter->icon())->toBe('check-circle');
});

test('text filter can be configured', function () {
    $filter = new TextFilter(
        name: 'customer_name',
        label: 'Customer Name',
        placeholder: 'Enter customer name...',
        icon: 'user'
    );

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('customer_name');
    expect($filter->type())->toBe('text');
    expect($filter->label())->toBe('Customer Name');
    expect($filter->placeholder())->toBe('Enter customer name...');
    expect($filter->icon())->toBe('user');
});

test('number range filter validates correctly', function () {
    $filter = new NumberRangeFilter(
        name: 'price_range',
        label: 'Price Range',
        min: 0,
        max: 1000
    );

    expect($filter)->toBeInstanceOf(AbstractFilter::class);
    expect($filter->name())->toBe('price_range');
    expect($filter->type())->toBe('number_range');
    expect($filter->validate(['min' => 10, 'max' => 100]))->toBeTrue();
    expect($filter->validate(['min' => 10]))->toBeTrue();
    expect($filter->validate(['max' => 100]))->toBeTrue();
    expect($filter->validate('invalid'))->toBeFalse();
});

test('product performance report includes status filter', function () {
    $report = new ProductPerformanceReport;
    $filters = $report->filters();

    expect($filters)->toHaveCount(3);

    // Check filter types
    expect($filters[0])->toBeInstanceOf(DateRangeFilter::class);
    expect($filters[1])->toBeInstanceOf(SkuFilter::class);
    expect($filters[2])->toBeInstanceOf(StatusFilter::class);
});

test('product performance report default filters include status', function () {
    $report = new ProductPerformanceReport;
    $defaults = $report->getDefaultFilters();

    expect($defaults)->toHaveKeys(['date_range', 'skus', 'statuses']);
    expect($defaults['statuses'])->toBe([]);
});

test('abstract filter has optional helper methods', function () {
    $filter = new SkuFilter;

    expect(method_exists($filter, 'placeholder'))->toBeTrue();
    expect(method_exists($filter, 'helpText'))->toBeTrue();
    expect(method_exists($filter, 'icon'))->toBeTrue();
    expect(method_exists($filter, 'hasDynamicOptions'))->toBeTrue();
});
