<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Shipment;

class OrderShipped
{
    public function __construct(
        public readonly Order $order,
        public readonly ?Shipment $shipment = null,
    ) {}
}
