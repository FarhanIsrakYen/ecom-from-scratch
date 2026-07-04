<?php

namespace App\Events;

use App\Models\Order;

class OrderCancelled
{
    public function __construct(public readonly Order $order) {}
}
