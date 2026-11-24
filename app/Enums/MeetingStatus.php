<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
