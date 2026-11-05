<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos (guard api) y roles de la clínica. El administrador pasa todo por
 * Gate::before (AppServiceProvider). Idempotente: se puede correr dos veces.
 *
 * El nombre del permiso del middleware de cada Controller debe existir
 * exactamente aquí.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        /* usuarios, roles y permisos */
        'users.index', 'users.store', 'users.show', 'users.update', 'users.destroy',
        'roles.index', 'roles.store', 'roles.show', 'roles.update', 'roles.destroy',
        'permissions.index',

        /* configuración */
        'branches.index', 'branches.store', 'branches.show', 'branches.update',
        'system-settings.update',
        'audit-logs.index', 'audit-logs.show',

        /* clientes y pacientes */
        'clients.index', 'clients.store', 'clients.show', 'clients.update', 'clients.destroy',
        'patients.index', 'patients.store', 'patients.show', 'patients.update', 'patients.destroy',
        'patients.change-owner',
        'species.index', 'species.store', 'species.update',

        /* veterinarios y servicios */
        'veterinarians.index', 'veterinarians.store', 'veterinarians.show', 'veterinarians.update', 'veterinarians.destroy',
        'services.index', 'services.store', 'services.show', 'services.update', 'services.destroy',

        /* agenda */
        'schedules.index', 'schedules.store', 'schedules.show', 'schedules.update', 'schedules.destroy',
        'appointments.index', 'appointments.store', 'appointments.show', 'appointments.update',
        'appointments.confirm', 'appointments.check-in', 'appointments.cancel',

        /* historia clínica */
        'consultations.index', 'consultations.store', 'consultations.show', 'consultations.update',
        'consultations.close', 'consultations.reopen', 'consultations.cancel',
        'consultation-items.store', 'consultation-items.update', 'consultation-items.destroy',
        'consultation-attachments.index', 'consultation-attachments.store', 'consultation-attachments.destroy',
        'patient-applications.index', 'patient-applications.store',
        'patient-documents.index', 'patient-documents.store', 'patient-documents.show', 'patient-documents.cancel',
        'document-templates.index', 'document-templates.store', 'document-templates.update', 'document-templates.destroy',

        /* recordatorios y avisos */
        'reminders.index', 'reminders.store', 'reminders.update', 'reminders.cancel',
        'client-notifications.index', 'client-notifications.show', 'client-notifications.resend',

        /* inventario */
        'products.index', 'products.store', 'products.show', 'products.update', 'products.destroy',
        'inventory.index', 'inventory.adjust', 'inventory.movements',
        'warehouses.index', 'warehouses.store', 'warehouses.update',

        /* caja y facturación */
        'cash-registers.index', 'cash-registers.open', 'cash-registers.close', 'cash-registers.show', 'cash-registers.view-all',
        'cash-movements.store', 'cash-movements.cancel',
        'invoice-stamps.index', 'invoice-stamps.store', 'invoice-stamps.update',
        'invoices.index', 'invoices.store', 'invoices.show', 'invoices.update', 'invoices.issue', 'invoices.cancel',
        'invoices.resend-sifen',
        'payments.index', 'payments.store', 'payments.cancel',

        /* reportes */
        'reports.clinical', 'reports.sales', 'reports.inventory', 'reports.cash',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        $admin = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        foreach (self::clinicRoles() as $name => $role) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'api'])
                ->syncPermissions(
                    Permission::where('guard_name', 'api')->whereIn('name', $role['permissions'])->get()
                );
        }
    }

    /**
     * Roles de la clínica (además del administrador). Un test debe verificar que
     * todos sus permisos existan en PERMISSIONS.
     */
    public static function clinicRoles(): array
    {
        return [
            'recepcionista' => [
                'description' => 'Recepción: agenda, clientes, pacientes, caja y facturación',
                'permissions' => [
                    'clients.index', 'clients.store', 'clients.show', 'clients.update',
                    'patients.index', 'patients.store', 'patients.show', 'patients.update', 'patients.change-owner',
                    'species.index',
                    'veterinarians.index', 'services.index', 'services.show',
                    'schedules.index', 'schedules.show',
                    'appointments.index', 'appointments.store', 'appointments.show', 'appointments.update',
                    'appointments.confirm', 'appointments.check-in', 'appointments.cancel',
                    'consultations.index', 'consultations.show',
                    'patient-applications.index', 'patient-documents.index', 'patient-documents.show',
                    'reminders.index', 'reminders.store', 'reminders.update', 'reminders.cancel',
                    'client-notifications.index', 'client-notifications.show', 'client-notifications.resend',
                    'products.index', 'products.show', 'inventory.index',
                    'cash-registers.index', 'cash-registers.open', 'cash-registers.close', 'cash-registers.show',
                    'cash-movements.store',
                    'invoices.index', 'invoices.store', 'invoices.show', 'invoices.update', 'invoices.issue',
                    'payments.index', 'payments.store',
                ],
            ],
            'veterinario' => [
                'description' => 'Veterinario: cola de atención, historia clínica, recetas y documentos',
                'permissions' => [
                    'clients.index', 'clients.store', 'clients.show', 'clients.update',
                    'patients.index', 'patients.store', 'patients.show', 'patients.update',
                    'species.index',
                    'veterinarians.index', 'services.index', 'services.show',
                    'schedules.index', 'schedules.show',
                    'appointments.index', 'appointments.show', 'appointments.check-in',
                    'consultations.index', 'consultations.store', 'consultations.show', 'consultations.update',
                    'consultations.close',
                    'consultation-items.store', 'consultation-items.update', 'consultation-items.destroy',
                    'consultation-attachments.index', 'consultation-attachments.store', 'consultation-attachments.destroy',
                    'patient-applications.index', 'patient-applications.store',
                    'patient-documents.index', 'patient-documents.store', 'patient-documents.show',
                    'document-templates.index',
                    'reminders.index', 'reminders.store', 'reminders.update',
                    'products.index', 'products.show', 'inventory.index',
                    'reports.clinical',
                ],
            ],
        ];
    }
}
