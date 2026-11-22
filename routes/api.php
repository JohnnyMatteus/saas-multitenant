<?php

use Illuminate\Support\Facades\Route;

/*
| API REST versionada. Cada módulo declara suas rotas; o prefixo de versão e a
| autenticação JWT ficam aqui para que nenhuma rota de domínio escape do tenant.
*/
Route::prefix('v1')
    ->middleware('auth.jwt')
    ->group(function (): void {
        require app_path('Modules/Identity/Http/routes.php');
        require app_path('Modules/Scheduling/Http/routes.php');
        require app_path('Modules/Branding/Http/routes.php');
        require app_path('Modules/Billing/Http/routes.php');
    });

Route::prefix('v1')->group(function (): void {
    require app_path('Modules/Identity/Http/integration-routes.php');
});
