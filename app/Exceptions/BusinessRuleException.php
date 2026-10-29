<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aksi ditolak karena aturan bisnis (mis. lead sudah dikonversi). Pesannya aman ditampilkan ke user.
 */
class BusinessRuleException extends RuntimeException
{
}
