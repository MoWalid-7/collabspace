<?php

namespace App\Enums;

enum ChannelType: string
{
    case Public = 'public';
    case Project = 'project';
    case Team = 'team';
    case Direct = 'direct';
}
