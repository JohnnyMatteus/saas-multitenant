<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use App\Modules\Scheduling\Events\WaitlistPromoted;
use App\Modules\Scheduling\Listeners\SendWaitlistPromotionEmail;
use App\Modules\Scheduling\Models\Booking;
use App\Modules\Scheduling\Models\ClassSeries;
use App\Modules\Scheduling\Models\ClassSession;
use App\Modules\Scheduling\Policies\BookingPolicy;
use App\Modules\Scheduling\Policies\ClassSeriesPolicy;
use App\Modules\Scheduling\Policies\ClassSessionPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ClassSeries::class, ClassSeriesPolicy::class);
        Gate::policy(ClassSession::class, ClassSessionPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        Event::listen(WaitlistPromoted::class, SendWaitlistPromotionEmail::class);
    }
}
