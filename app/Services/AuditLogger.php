<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Registro de auditoría (FASE 25). Los modelos con el trait Auditable se
 * registran solos (alta, modificación, baja, restauración); los eventos sin
 * modelo propio (login, sincronización de roles y permisos) se registran
 * explícitamente con record().
 */
class AuditLogger
{
    /**
     * Campos que nunca se guardan con su valor.
     */
    private const MASKED = ['password', 'remember_token', 'token'];

    /**
     * Campos que no aportan a la auditoría (los fija Eloquent).
     */
    private const IGNORED = ['created_at', 'updated_at', 'deleted_at'];

    public function record(
        AuditEvent $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            'event' => $event,
            'auditable_type' => $auditable ? $this->type($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'user_id' => ($user ?? auth()->user())?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 252) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Evento de un modelo Auditable. En una modificación solo se guardan los
     * campos que cambiaron (antes y después); sin cambios relevantes, nada.
     */
    public function modelEvent(AuditEvent $event, Model $model): void
    {
        $exclude = [...self::IGNORED, ...$model->auditExclude()];
        $context = $model->auditContext();

        [$old, $new] = match ($event) {
            AuditEvent::CREATED => [null, $this->clean($model->getAttributes(), $exclude)],
            AuditEvent::DELETED => [$this->clean($model->getRawOriginal(), $exclude), null],
            AuditEvent::RESTORED => [null, $this->clean($model->getAttributes(), $exclude)],
            default => $this->changes($model, $exclude),
        };

        if ($event === AuditEvent::UPDATED && $new === []) {
            return;
        }

        // Identificación de registros sin clave propia (pivotes).
        if ($context !== []) {
            $old = $old !== null ? [...$context, ...$old] : null;
            $new = $new !== null ? [...$context, ...$new] : null;
        }

        $this->record($event, $model, $old, $new);
    }

    /**
     * @return array{0: array, 1: array}
     */
    private function changes(Model $model, array $exclude): array
    {
        $new = $this->clean($model->getChanges(), $exclude);
        $old = [];

        foreach (array_keys($new) as $key) {
            $old[$key] = in_array($key, self::MASKED, true) ? '********' : $model->getRawOriginal($key);
        }

        return [$old, $new];
    }

    private function clean(array $values, array $exclude): array
    {
        $values = array_diff_key($values, array_flip($exclude));

        foreach (self::MASKED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '********';
            }
        }

        return $values;
    }

    private function type(Model $model): string
    {
        return method_exists($model, 'auditType') ? $model->auditType() : Str::snake(class_basename($model));
    }
}
