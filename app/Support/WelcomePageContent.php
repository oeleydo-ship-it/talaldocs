<?php

namespace App\Support;

class WelcomePageContent
{
    public const SUBTITLE = 'Get up and running with your documentation in minutes.';

    public static function markdown(): string
    {
        return <<<'MD'
# Welcome

Get up and running with your documentation in minutes.

## Quickstart

1. **Open the editor** — Click **Editor** in your project dashboard to start writing.

2. **Customize branding** — Set your logo, colors, and docs template under **Project settings → Branding**.

3. **Publish your first page** — Click **Publish** when you are ready to share with readers.

4. **Share your docs** — Send your public URL to customers and teammates.

## What's next

- Add more pages in the sidebar tree
- Import existing Markdown files
- Configure visibility and custom domains
MD;
    }
}
