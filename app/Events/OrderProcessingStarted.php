<?php

namespace App\Events;

use App\Models\Order;

class OrderProcessingStarted
{
    public function __construct(public readonly Order $order) {}
}
