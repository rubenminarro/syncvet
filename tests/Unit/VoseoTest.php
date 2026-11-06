<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Mensajes al usuario en español y con voseo (CLAUDE.md §3, R-004; FASE 1,
 * entregas 1.1 y 1.2). Revisa los textos de la aplicación (las cadenas con
 * espacios, que son las que llegan al usuario; no los comentarios ni los
 * identificadores) y busca:
 *
 * - Palabras que solo son de tú o de usted ("tienes", "usted", "asegúrese"), en
 *   cualquier lugar.
 * - Imperativos de tú y de usted de los verbos de VERBS ("Ingresa", "registre"),
 *   solo al comienzo de una oración o de una cláusula, o después de "por favor",
 *   "no" o "que": en otro lugar son la tercera persona ("La consulta registra la
 *   fecha") y no se marcan (deuda 12).
 * - Mensajes en inglés: palabras frecuentes del inglés que no existen en español.
 *
 * Los imperativos salen de conjugar cada infinitivo de VERBS (deuda 9). Un verbo
 * nuevo en un mensaje se agrega a la lista como infinitivo; uno irregular, a
 * IRREGULAR_VERBS.
 */
class VoseoTest extends TestCase
{
    /**
     * Palabras que solo existen en tú o en usted.
     */
    private const TU_OR_USTED_WORDS = [
        'tú', 'ti', 'contigo', 'usted',
        'tienes', 'puedes', 'debes', 'quieres', 'eres', 'has', 'sabes', 'necesitas', 'estés', 'seas',
        'asegúrate', 'asegúrese', 'comunícate', 'comuníquese', 'fíjate', 'fíjese', 'contáctanos', 'contáctenos',
        'acuérdate', 'acuérdese', 'inténtalo', 'inténtelo',
    ];

    /**
     * Verbos regulares de los mensajes, en infinitivo. Quedan afuera los que dan
     * un sustantivo frecuente al comienzo de una oración ("Consulta", "Carga",
     * "Prueba", "Cierre", "Marca", "Activa", "Ajuste").
     */
    private const VERBS = [
        'ingresar', 'registrar', 'revisar', 'intentar', 'reintentar', 'seleccionar', 'contactar', 'verificar', 'usar',
        'utilizar', 'completar', 'indicar', 'confirmar', 'agregar', 'esperar', 'adjuntar', 'cambiar', 'buscar',
        'guardar', 'presionar', 'aceptar', 'cancelar', 'actualizar', 'iniciar', 'asignar', 'eliminar', 'borrar',
        'editar', 'modificar', 'informar', 'avisar', 'solicitar', 'llamar', 'anotar', 'pagar', 'anular', 'descargar',
        'especificar', 'proporcionar', 'rellenar', 'llenar', 'olvidar', 'aguardar', 'escribir', 'subir',
        'abrir', 'añadir', 'emitir', 'imprimir', 'definir', 'acudir', 'leer', 'responder',
    ];

    /**
     * Verbos irregulares o con cambio de raíz: imperativo de tú, imperativo de
     * usted y negativo de tú.
     */
    private const IRREGULAR_VERBS = [
        'volver' => ['vuelve', 'vuelva', 'vuelvas'],
        'elegir' => ['elige', 'elija', 'elijas'],
        'corregir' => ['corrige', 'corrija', 'corrijas'],
        'escoger' => ['escoge', 'escoja', 'escojas'],
        'pedir' => ['pide', 'pida', 'pidas'],
        'repetir' => ['repite', 'repita', 'repitas'],
        'recordar' => ['recuerda', 'recuerde', 'recuerdes'],
        'comprobar' => ['comprueba', 'compruebe', 'compruebes'],
        'enviar' => ['envía', 'envíe', 'envíes'],
        'introducir' => ['introduce', 'introduzca', 'introduzcas'],
        'hacer' => ['haz', 'haga', 'hagas'],
        'poner' => ['pon', 'ponga', 'pongas'],
        'decir' => ['di', 'diga', 'digas'],
        'tener' => ['ten', 'tenga', 'tengas'],
        'venir' => ['ven', 'venga', 'vengas'],
        'ir' => ['ve', 'vaya', 'vayas'],
    ];

    /**
     * Palabras frecuentes del inglés que no existen en español.
     */
    private const ENGLISH_WORDS = [
        'the', 'is', 'are', 'be', 'been', 'must', 'should', 'please', 'your', 'you', 'this', 'that', 'with', 'for',
        'of', 'to', 'and', 'not', 'have', 'was', 'were', 'cannot', 'could', 'field', 'invalid', 'required', 'already',
        'missing', 'failed', 'does', 'an', 'in', 'on', 'by', 'from', 'enter', 'select', 'try', 'again',
    ];

    /**
     * Nombres propios en inglés que aparecen en los mensajes.
     */
    private const PROPER_NAMES = ['Have I Been Pwned'];

    /**
     * Comienzo de una oración o de una cláusula, con "por favor" opcional.
     */
    private const CLAUSE_START = '(?:^|[.:;,!?¡¿(]\s*)(?:por\s+favor,?\s+)?';

    public function test_the_forms_are_detected(): void
    {
        // Las de la entrega 1.1.
        $this->assertSame(['tienes'], $this->forbiddenForms('No tienes permisos.'));
        $this->assertSame(['registre'], $this->forbiddenForms('Si falleció, registre la fecha.'));
        $this->assertSame(['use'], $this->forbiddenForms('Una empresa no lleva nombre: use la razón social.'));
        $this->assertSame(['ingresa'], $this->forbiddenForms('Ingresa tu email.'));

        // Fuera de la lista de la 1.1 (deuda 9).
        $this->assertSame(['completa'], $this->forbiddenForms('Completa tu email.'));
        $this->assertSame(['usa'], $this->forbiddenForms('Una empresa no lleva nombre: usa la razón social.'));
        $this->assertSame(['indique'], $this->forbiddenForms('Por favor, indique el motivo.'));
        $this->assertSame(['usted'], $this->forbiddenForms('Usted debe indicar su email.'));
        $this->assertSame(['busque'], $this->forbiddenForms('No busque al cliente por su nombre.'));
        $this->assertSame(['olvides'], $this->forbiddenForms('No olvides la fecha.'));
        $this->assertSame(['ingrese'], $this->forbiddenForms('Le pedimos que ingrese su email.'));
        $this->assertSame(['elija'], $this->forbiddenForms('Elija otra fecha.'));
        $this->assertSame(['asegúrate'], $this->forbiddenForms('Antes de emitir, asegúrate de revisar los datos.'));
        $this->assertSame(['english'], $this->forbiddenForms('Please enter your email.'));
        $this->assertSame(['english'], $this->forbiddenForms('The email field is required.'));

        // Voseo, tercera persona e identificadores: no se marcan (deuda 12).
        $this->assertSame([], $this->forbiddenForms('No tenés permisos. Ingresá tu email. Registrá la fecha. Usá la razón social.'));
        $this->assertSame([], $this->forbiddenForms('Completá el motivo y elegí otra fecha. No te olvidés del email.'));
        $this->assertSame([], $this->forbiddenForms('La consulta registra la fecha y el veterinario revisa los datos.'));
        $this->assertSame([], $this->forbiddenForms('El sistema no registra pagos y verifica el timbrado.'));
        $this->assertSame([], $this->forbiddenForms('La historia completa se envía por email.'));
        $this->assertSame([], $this->forbiddenForms('CLIENT_IN_USE'));
        $this->assertSame([], $this->forbiddenForms('Have I Been Pwned no respondió.'));
    }

    public function test_messages_use_voseo(): void
    {
        $found = [];

        foreach ($this->files() as $path) {
            foreach ($this->texts($path) as [$line, $text]) {
                foreach ($this->forbiddenForms($text) as $form) {
                    $found[] = "{$path}:{$line} \"{$form}\" en {$text}";
                }
            }
        }

        $this->assertSame([], $found, 'Mensajes con tú, con usted o en inglés.');
    }

    /**
     * Las formas prohibidas de un texto; "english" si está en inglés.
     *
     * @return list<string>
     */
    private function forbiddenForms(string $text): array
    {
        $text = str_replace(self::PROPER_NAMES, '', trim($text, "'\""));
        $forms = [];

        foreach (self::TU_OR_USTED_WORDS as $word) {
            if (preg_match('/(?<![\p{L}_])' . $word . '(?![\p{L}_])/iu', $text)) {
                $forms[] = $word;
            }
        }

        foreach ($this->imperatives() as [$affirmativeTu, $usted, $negativeTu]) {
            $patterns = [
                // Afirmativos: al comienzo de una cláusula.
                $affirmativeTu => self::CLAUSE_START . $affirmativeTu,
                // Usted: también negativo ("no registre") o subordinado ("que registre").
                $usted => '(?:' . self::CLAUSE_START . '(?:no\s+)?|\bque\s+)' . $usted,
                // Negativo de tú: después de "no".
                $negativeTu => '\bno\s+' . $negativeTu,
            ];

            foreach ($patterns as $form => $pattern) {
                if (preg_match('/' . $pattern . '(?![\p{L}_])/iu', $text)) {
                    $forms[] = $form;
                }
            }
        }

        // Sin los identificadores en mayúsculas ("CLIENT_IN_USE"), que no son texto en inglés.
        $prose = preg_replace('/\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+\b/', '', $text);
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower($prose), -1, PREG_SPLIT_NO_EMPTY);

        if (array_intersect($words, self::ENGLISH_WORDS) !== []) {
            $forms[] = 'english';
        }

        return array_values(array_unique($forms));
    }

    /**
     * Imperativo de tú, imperativo de usted y negativo de tú de cada verbo.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function imperatives(): array
    {
        $forms = array_values(self::IRREGULAR_VERBS);

        foreach (self::VERBS as $verb) {
            $stem = substr($verb, 0, -2);

            if (str_ends_with($verb, 'ar')) {
                // Cambios de ortografía ante "e": buscar → busque, pagar → pague, utilizar → utilice.
                $subjunctiveStem = preg_replace(['/c$/', '/g$/', '/z$/'], ['qu', 'gu', 'c'], $stem);
                $forms[] = [$stem . 'a', $subjunctiveStem . 'e', $subjunctiveStem . 'es'];
            } else {
                $forms[] = [$stem . 'e', $stem . 'a', $stem . 'as'];
            }
        }

        return $forms;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/bootstrap/app.php'];

        foreach ((new Finder())->files()->in([$root . '/app', $root . '/lang/es'])->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }

        $this->assertGreaterThan(1, count($files));

        return $files;
    }

    /**
     * Las cadenas con al menos un espacio, con su línea.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function texts(string $path): array
    {
        $texts = [];

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && str_contains($token[1], ' ')) {
                $texts[] = [$token[2], $token[1]];
            }
        }

        return $texts;
    }
}
