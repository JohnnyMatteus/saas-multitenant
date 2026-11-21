<?php

use App\Modules\Identity\Http\Controllers\ConfirmWhatsAppLinkController;
use App\Modules\Identity\Http\Middleware\AuthenticateWhatsAppAgent;
use Illuminate\Support\Facades\Route;

// Fora do grupo auth.jwt: quem chama é a conta de serviço do agente, sem usuário.
Route::post('/integrations/whatsapp/links', ConfirmWhatsAppLinkController::class)
    ->middleware([AuthenticateWhatsAppAgent::class, 'throttle:whatsapp-link'])
    ->name('integrations.whatsapp.links.confirm');
