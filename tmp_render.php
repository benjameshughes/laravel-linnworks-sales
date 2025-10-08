<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use Livewire\Livewire;
use Carbon\Carbon;

Carbon::setTestNow(Carbon::parse('2024-11-10 12:00:00'));

Order::query()->delete();

Order::factory()->create([
    'total_charge' => 120,
    'received_date' => Carbon::now()->subDays(2)->setTime(9, 15),
    'is_processed' => true,
    'channel_name' => 'SHOPIFY',
]);
Order::factory()->create([
    'total_charge' => 80,
    'received_date' => Carbon::now()->subDay()->setTime(11, 5),
    'is_processed' => false,
    'channel_name' => 'AMAZON',
]);

$html = Livewire::test(App\Livewire\Analytics\SalesAnalytics::class)
    ->set('datePreset', 'custom')
    ->set('startDate', Carbon::now()->subDays(2)->toDateString())
    ->set('endDate', Carbon::now()->subDay()->toDateString())
    ->html();

file_put_contents('tmp_render.html', $html);
