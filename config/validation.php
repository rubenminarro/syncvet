<?php

return [

    // Validar que el dominio del email exista (consulta DNS). phpunit.xml lo desactiva
    // para que los tests no dependan de la red.
    'email_dns' => (bool) env('EMAIL_DNS_CHECK', true),

];
