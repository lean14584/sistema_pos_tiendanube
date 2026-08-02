<?php

namespace App\Enums;

enum CashMovementSource: string
{
    case Venta = 'venta';
    case Compra = 'compra';
    case Manual = 'manual';
    case Devolucion = 'devolucion';
}
