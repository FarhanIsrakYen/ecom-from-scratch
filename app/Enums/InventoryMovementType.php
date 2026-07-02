<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Reserved = 'reserved';
    case Released = 'released';
    case Sold = 'sold';
    case Adjusted = 'adjusted';
}
