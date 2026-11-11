<?php

namespace App\Services;

use App\Enums\ClientKind;
use App\Exceptions\ClientException;
use App\Models\Client;
use App\Support\DeletedSuffix;
use Illuminate\Support\Facades\DB;

/**
 * Clientes (tutores): personas o empresas. La baja libera los campos únicos con
 * un sufijo y no se permite con el cliente en uso (datos maestros).
 */
class ClientService
{
    public function create(array $data): Client
    {
        return Client::create($this->withoutOtherKindFields($data, $this->kindOf($data)));
    }

    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {

            $kind = $this->kindOf($data, $client);

            if ($kind !== $client->kind) {
                // Las facturas ya muestran el nombre del cliente: con ellas, el tipo queda fijo.
                if ($client->invoices()->exists()) {
                    throw new ClientException(
                        'El cliente ya tiene facturas: no se puede cambiar de persona a empresa ni al revés.',
                        422,
                        'CLIENT_KIND_NOT_EDITABLE'
                    );
                }

                $data = $this->withoutOtherKindFields($data, $kind);
            }

            $client->update($data);

            return $client;
        });
    }

    /**
     * Solo sin uso: pacientes, turnos (FASE 3, entrega 3.2a; pregunta
     * 21), consultas o facturas. El uso se revisa dentro de la transacción, con la fila del
     * cliente bloqueada (lockForUpdate): el alta de un turno toma el cliente con sharedLock,
     * así que un turno simultáneo no queda en un cliente eliminado.
     */
    public function delete(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $client = Client::query()->whereKey($client->getKey())->lockForUpdate()->firstOrFail();

            if ($client->patients()->exists()
                || $client->appointments()->exists()
                || $client->consultations()->exists()
                || $client->invoices()->exists()) {
                throw new ClientException(
                    'El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse.',
                    409,
                    'CLIENT_IN_USE'
                );
            }

            $suffix = DeletedSuffix::for($client);

            // Sin cortar: las columnas (50) admiten el documento (20) y el sufijo con un
            // id de hasta 9 dígitos (21 + id). Con el id, dos bajas del mismo documento
            // no chocan con el índice único, ni siquiera en el mismo segundo. El modelo
            // recalcula la base con el mismo sufijo (DocumentBase), así el documento
            // queda libre en cualquier forma (cédula o RUC, R-018).
            $client->document_number = $client->document_number . $suffix;
            $client->email = $client->email . $suffix;
            $client->save();

            $client->delete();
        });
    }

    private function kindOf(array $data, ?Client $client = null): ClientKind
    {
        return isset($data['kind']) ? ClientKind::from($data['kind']) : ($client?->kind ?? ClientKind::PERSON);
    }

    /**
     * Una persona no lleva razón social y una empresa no lleva nombre ni apellido.
     */
    private function withoutOtherKindFields(array $data, ClientKind $kind): array
    {
        $fields = $kind === ClientKind::COMPANY ? ['first_name', 'last_name'] : ['business_name'];

        return [...$data, ...array_fill_keys($fields, null), 'kind' => $kind];
    }
}
