<?php

namespace App\Support;

use App\Enums\ConsultationItemBillingStatus;
use App\Models\ConsultationItem;

/**
 * Los importes de la consulta (FASE 4, entrega 4.3), en un solo lugar: los de cada ítem y los de la
 * consulta, siempre con BCMath sobre strings (§3), nunca float, en guaraníes.
 *
 * - Un ítem (line): subtotal = cantidad × precio unitario, redondeado a 0 decimales (half-up,
 *   Decimal::round); total = subtotal − descuento. La cantidad, con 3 decimales; los importes, con 2
 *   (la escala de sus columnas, aunque en guaraníes no tengan fracción).
 * - La consulta (of): la suma de los ítems que se cobran (pending e invoiced; los no_charge de una
 *   consulta anulada, no): subtotal, descuento y total, sin volver a redondear (cada ítem ya está
 *   redondeado).
 */
final class ConsultationTotals
{
    /**
     * Escala del redondeo de los importes: guaraníes, sin decimales (R-004).
     */
    public const AMOUNT_SCALE = 0;

    /**
     * @return array{quantity: string, unit_price: string, discount: string, subtotal: string, total: string}
     */
    public static function line(string $quantity, string $unitPrice, string $discount): array
    {
        $subtotal = Decimal::round(bcmul($quantity, $unitPrice, 6), self::AMOUNT_SCALE);
        $discount = Decimal::round($discount, self::AMOUNT_SCALE);

        return [
            'quantity' => Decimal::round($quantity, 3),
            'unit_price' => Decimal::round($unitPrice, 2),
            'discount' => self::money($discount),
            'subtotal' => self::money($subtotal),
            'total' => self::money(bcsub($subtotal, $discount, 2)),
        ];
    }

    /**
     * @param iterable<ConsultationItem> $items
     * @return array{subtotal: string, discount: string, total: string}
     */
    public static function of(iterable $items): array
    {
        $subtotal = '0';
        $discount = '0';
        $total = '0';

        foreach ($items as $item) {
            if ($item->billing_status === ConsultationItemBillingStatus::NO_CHARGE) {
                continue;
            }

            $discount = bcadd($discount, (string) $item->discount, 2);
            $total = bcadd($total, (string) $item->total, 2);
            $subtotal = bcadd($subtotal, bcadd((string) $item->total, (string) $item->discount, 2), 2);
        }

        return ['subtotal' => self::money($subtotal), 'discount' => self::money($discount), 'total' => self::money($total)];
    }

    /**
     * Un importe con la escala de su columna (decimal(14,2)): "150000.00".
     */
    private static function money(string $value): string
    {
        return bcadd($value, '0', 2);
    }
}
