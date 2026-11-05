<?php

namespace App\Exceptions;

/**
 * Errores del login: credenciales incorrectas (INVALID_CREDENTIALS, 422) y
 * demasiados intentos fallidos (AUTH_TOO_MANY_ATTEMPTS, 429, con Retry-After).
 */
class AuthException extends AppException
{
}
