<?php

namespace App\Support;

use App\Models\Workspace;

class CurrentWorkspace
{
    public function __construct(public Workspace $workspace) {}
}
