<?php

use Tests\Pest\Helpers\LinnworksTestCase;

uses(LinnworksTestCase::class)->in(
    'Feature/Services',
    'Feature/Commands',
    'Feature/Livewire',
    'Unit/Services',
);

uses(Tests\TestCase::class)->in('Feature/Models');
