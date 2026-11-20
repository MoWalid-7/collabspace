<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
}
