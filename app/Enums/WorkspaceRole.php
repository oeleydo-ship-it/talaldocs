<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canEditContent(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }

    public function canManageSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageBilling(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
