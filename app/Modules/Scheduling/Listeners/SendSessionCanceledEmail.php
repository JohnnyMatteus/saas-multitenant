<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Scheduling\Events\SessionCanceledByStudio;
use App\Modules\Scheduling\Mail\SessionCanceledMail;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Tenancy\Models\Tenant;

final class SendSessionCanceledEmail extends SendsBookingMail
{
    public function handle(SessionCanceledByStudio $event): void
    {
        $this->deliver($event->tenantId, function (Tenant $tenant) use ($event): array {
            $session = ClassSession::query()->findOrFail($event->sessionId);
            $member = User::query()->findOrFail($event->memberId);

            return [$member, new SessionCanceledMail(
                $tenant->name, $member->name, $session->title, $this->localStart($session, $tenant),
                $event->wasWaitlisted, $session->cancel_reason,
            )];
        });
    }
}
