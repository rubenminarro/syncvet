<?php

namespace App\Services;

use App\Exceptions\ClinicException;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CurrentClinic;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una clínica (D1). La llama el comando clinic:create (entrega 0.4): no
 * hay alta por API en el MVP.
 */
class ClinicService
{
    /**
     * Crea la clínica operativa: la clínica, la sucursal SUC-01 "Casa central"
     * (establecimiento 001), el depósito DEP-01 "Principal" y el usuario
     * administrador. Todo o nada (una transacción).
     *
     * @param array{name: string, legal_name: string, ruc: string, ruc_dv: string, timezone?: string} $data
     * @param array{email: string, first_name: string, last_name: string, password: string} $admin
     */
    public function create(array $data, #[\SensitiveParameter] array $admin): Clinic
    {
        $this->ensureValidRuc($data['ruc'], $data['ruc_dv']);
        $this->ensureAdminEmailIsAscii($admin['email']);

        return DB::transaction(function () use ($data, $admin) {
            // Si dos altas simultáneas pasan estas verificaciones, los índices únicos de
            // clinics.ruc y users.email frenan a la segunda y la transacción se revierte.
            $this->ensureRucIsFree($data['ruc']);
            // El email se guarda en minúsculas (User): la verificación también, para que
            // "Ana@x.com" y "ana@x.com" sean el mismo login.
            $this->ensureAdminEmailIsFree(User::normalizeEmail($admin['email']));

            $clinic = Clinic::create($data);

            return CurrentClinic::run($clinic, function () use ($clinic, $admin) {
                $branch = Branch::create([
                    'code' => 'SUC-01',
                    'name' => 'Casa central',
                    'establishment' => '001',
                    'is_default' => true,
                    'is_active' => true,
                ]);

                Warehouse::create([
                    'branch_id' => $branch->id,
                    'code' => 'DEP-01',
                    'name' => 'Principal',
                    'is_default' => true,
                    'is_active' => true,
                ]);

                $user = new User([
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'name' => trim("{$admin['first_name']} {$admin['last_name']}"),
                    'email' => $admin['email'],
                    'password' => $admin['password'],
                    'current_branch_id' => $branch->id,
                ]);
                $user->clinic_id = $clinic->id;
                $user->save();

                $user->branches()->attach($branch->id);
                $user->assignRole('administrador');

                return $clinic;
            });
        });
    }

    /**
     * Dígito verificador del RUC paraguayo (módulo 11, algoritmo de la SET).
     */
    public static function rucCheckDigit(string $ruc): int
    {
        $total = 0;
        $factor = 2;

        foreach (array_reverse(str_split($ruc)) as $digit) {
            if ($factor > 11) {
                $factor = 2;
            }

            $total += (int) $digit * $factor;
            $factor++;
        }

        $remainder = $total % 11;

        return $remainder > 1 ? 11 - $remainder : 0;
    }

    private function ensureValidRuc(string $ruc, string $dv): void
    {
        if (! preg_match('/^\d{1,20}$/', $ruc)
            || ! preg_match('/^\d$/', $dv)
            || self::rucCheckDigit($ruc) !== (int) $dv) {
            throw new ClinicException(
                'El RUC no es válido: revisá el número y el dígito verificador.',
                422,
                'CLINIC_RUC_INVALID'
            );
        }
    }

    private function ensureRucIsFree(string $ruc): void
    {
        if (Clinic::where('ruc', $ruc)->exists()) {
            throw new ClinicException('Ya hay una clínica con ese RUC.', 422, 'CLINIC_RUC_TAKEN');
        }
    }

    /**
     * El email es el login: solo ASCII, como en LoginRequest. La collation de MySQL
     * iguala "ā" y "a": con acentos, dos emails distintos serían el mismo login.
     */
    private function ensureAdminEmailIsAscii(string $email): void
    {
        if (! Str::isAscii($email)) {
            throw new ClinicException(
                'El email del administrador no puede tener acentos ni caracteres especiales.',
                422,
                'CLINIC_ADMIN_EMAIL_INVALID'
            );
        }
    }

    /**
     * El email es el login: único en todo el sistema, también entre usuarios eliminados.
     */
    private function ensureAdminEmailIsFree(string $email): void
    {
        // Consulta global a propósito: User no tiene scope de clínica (ver User).
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new ClinicException(
                'Ya hay un usuario con ese email.',
                422,
                'CLINIC_ADMIN_EMAIL_TAKEN'
            );
        }
    }
}
