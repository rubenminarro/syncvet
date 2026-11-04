# Guía de pruebas con Postman

Requests por fase, encadenados. Todos llevan `Accept: application/json` y, salvo el login, `Authorization: Bearer {{token}}`.
Las respuestas siguen el contrato de `CLAUDE.md` §4.

## FASE 0 — Autenticación

PENDIENTE: se completa al cerrar la FASE 0 (login, logout, me).

## FASE 1 — Clientes y pacientes

Endpoints ya presentes en el paquete base; la guía se valida al cerrar la FASE 1.

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/clients` | `{"document_number":"1234567","first_name":"Ana","last_name":"Gómez","email":"ana@ejemplo.com.py","phone":"+595981000000"}` | 201, `data.display_name` = "Ana Gómez" |
| 2 | `POST /api/clients` | `{"kind":"company","document_number":"80012345-6","business_name":"Haras del Sur SA","email":"haras@ejemplo.com.py","phone":"+595981000001"}` | 201 |
| 3 | `POST /api/clients` con `first_name` en una empresa | | 422 `VALIDATION_ERROR` |
| 4 | `GET /api/species` | | 200, especies con sus razas |
| 5 | `POST /api/clients/{id}/patients` | `{"name":"Toby","species_id":1,"birth_date":"2023-01-10"}` | 201, `data.code` = `PAC-000001` |
| 6 | `POST /api/clients/{id}/patients` con una raza de otra especie | | 422 `PATIENT_BREED_SPECIES_MISMATCH` |
| 7 | `POST /api/patients/{id}/change-owner` | `{"client_id": <empresa>}` | 200, `data.client.display_name` = "Haras del Sur SA" |
| 8 | `POST /api/patients/{id}/change-owner` con el mismo dueño | | 422 `PATIENT_SAME_OWNER` |
| 9 | `PATCH /api/patients/{id}` con `client_id` | | 422 `VALIDATION_ERROR` |
| 10 | `DELETE /api/clients/{id}` con pacientes | | 409 `CLIENT_IN_USE` |
| 11 | `GET /api/clients?search=Toby` | | 200, encuentra a Ana por su mascota |
