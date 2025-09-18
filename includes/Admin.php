<?php
declare(strict_types=1);

namespace ProtectedDocs;

class Admin
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'init_admin']);
    }

    public function init_admin(): void
    {
        // Admin-specific initialization
    }
}
