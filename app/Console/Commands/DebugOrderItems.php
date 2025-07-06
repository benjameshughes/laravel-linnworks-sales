<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class DebugOrderItems extends Command
{
    protected $signature = 'debug:order-items';
    protected $description = 'Debug order items structure';

    public function handle(): int
    {
        $order = Order::whereNotNull('items')->first();
        
        if (!$order) {
            $this->error('No orders with items found');
            return Command::FAILURE;
        }
        
        $this->info("Order ID: {$order->id}");
        $this->info("Order Number: {$order->order_number}");
        
        $items = $order->items;
        $this->info("Items type: " . gettype($items));
        
        if (is_string($items)) {
            $this->info("Items (string): " . substr($items, 0, 200) . '...');
            $decoded = json_decode($items, true);
            $this->info("Decoded items: " . json_encode($decoded, JSON_PRETTY_PRINT));
        } else {
            $this->info("Items (array): " . json_encode($items, JSON_PRETTY_PRINT));
        }
        
        return Command::SUCCESS;
    }
}