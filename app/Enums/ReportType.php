<?php

namespace App\Enums;

enum ReportType: string
{
    case Tasks = 'tasks';
    case Projects = 'projects';
    case Time = 'time';
    case Workers = 'workers';
}
