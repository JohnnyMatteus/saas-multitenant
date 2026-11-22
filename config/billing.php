<?php

return [

    /*
    | Eventos do processador de pagamentos (payments-events-go) por RabbitMQ.
    | Sem `amqp_url` o consumidor não sobe. Não foi exercitado contra o processador real.
    */
    'payments' => [
        'amqp_url' => env('PAYMENTS_AMQP_URL'),
        'exchange' => env('PAYMENTS_EXCHANGE', 'payments.events'),
        'binding_key' => 'ledger.entry_recorded',
        'queue' => env('PAYMENTS_QUEUE', 'saas-billing'),
        'delivery_limit' => 5,
        'prefetch' => 10,
    ],

];
