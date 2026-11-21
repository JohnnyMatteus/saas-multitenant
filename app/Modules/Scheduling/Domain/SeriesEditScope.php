<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

enum SeriesEditScope: string
{
    case Occurrence = 'occurrence';
    case Following = 'following';
    case All = 'all';
}
