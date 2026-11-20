<?php

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case OpenToOffers = 'open_to_offers';
}
