<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Contraseña de un usuario: la regla del proyecto (Password::defaults(), en
 * AppServiceProvider, R-009: al menos 12 caracteres, con mayúsculas, minúsculas,
 * números y símbolos, y que no aparezca en filtraciones conocidas), sus mensajes
 * con voseo y la contraseña generada. La usan clinic:create y el DemoSeeder; en la
 * FASE 2, el ABM de usuarios.
 */
final class UserPassword
{
    /**
     * Largo de la contraseña generada (clinic:create sin interacción).
     */
    public const GENERATED_LENGTH = 20;

    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return ['required', 'string', Password::defaults()];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'password'): array
    {
        return [
            "{$attribute}.required" => 'Ingresá la contraseña.',
            "{$attribute}.string" => 'Ingresá una contraseña válida.',
            "{$attribute}.min" => 'La contraseña debe tener al menos :min caracteres.',
            "{$attribute}.password.mixed" => 'La contraseña debe tener mayúsculas y minúsculas.',
            "{$attribute}.password.letters" => 'La contraseña debe tener al menos una letra.',
            "{$attribute}.password.numbers" => 'La contraseña debe tener al menos un número.',
            "{$attribute}.password.symbols" => 'La contraseña debe tener al menos un símbolo.',
            "{$attribute}.password.uncompromised" => 'Esa contraseña aparece en filtraciones de datos conocidas: elegí otra.',
        ];
    }

    /**
     * El primer error de la contraseña, o null si cumple la regla.
     */
    public static function error(#[\SensitiveParameter] mixed $password): ?string
    {
        $validator = Validator::make(['password' => $password], ['password' => self::rules()], self::messages());

        return $validator->fails() ? $validator->errors()->first('password') : null;
    }

    /**
     * Juegos de caracteres de la contraseña generada: sin los que se confunden al
     * copiarlos (0/O, 1/l/I) y con símbolos que no molestan en la consola.
     */
    private const CHARSETS = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!#$%*+-=?@_',
    ];

    /**
     * Contraseña aleatoria que cumple la regla: al menos un carácter de cada juego
     * (mayúscula, minúscula, número y símbolo), con random_int (CSPRNG) y mezclada.
     */
    public static function generate(): string
    {
        $all = implode('', self::CHARSETS);
        $chars = array_map(fn (string $set) => $set[random_int(0, strlen($set) - 1)], self::CHARSETS);

        while (count($chars) < self::GENERATED_LENGTH) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
