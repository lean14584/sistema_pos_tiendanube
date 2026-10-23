<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una venta intenta descontar más stock del que hay
 * disponible en la sucursal. Debe capturarse donde se inicia la venta
 * (ej. Pos\Index::cobrarInterno()) y mostrarse como error de validación,
 * nunca dejarse propagar como un 500.
 */
class InsufficientStockException extends RuntimeException {}
