<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio incumplida al aplicar un ajuste de inventario.
 *
 * Su mensaje está escrito EN ESPAÑOL Y PARA EL USUARIO, así que es el único
 * tipo de error del que AdjustmentController::approve() puede devolver el
 * texto tal cual. Cualquier otra excepción (QueryException con el SQL dentro,
 * TypeError, etc.) es un fallo técnico y solo debe llegar al log.
 */
class AdjustmentException extends RuntimeException
{
}
