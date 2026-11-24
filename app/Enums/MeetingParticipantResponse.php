<?php

namespace App\Enums;

enum MeetingParticipantResponse: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
