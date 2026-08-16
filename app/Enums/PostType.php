<?php

namespace App\Enums;

enum PostType: string
{
    case Announcement = 'announcement';
    case Changelog = 'changelog';
}
