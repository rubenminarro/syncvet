<?php

namespace App\Support;

/**
 * Nombre y apellido de una persona, la misma regla para clientes y usuarios (FASE 2,
 * entrega 2.2a): letras separadas por espacios, o por un apóstrofo o un guion
 * (D'Alessandro, Ana-María). El apóstrofo y el guion van siempre entre letras: no al
 * principio ni al final, ni repetidos.
 */
final class PersonName
{
    public const REGEX = '/^[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+(?:(?:\s+|[\'-])[a-zA-ZñÑáéíóúÁÉÍÓÚüÜ]+)*$/u';
}
