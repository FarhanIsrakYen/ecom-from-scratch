<?php

namespace App\Events;

use App\Models\Order;

class OrderDelivered
{
    public function __construct(public readonly Order $order) {}
}
