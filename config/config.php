<?php
/**
 * Configuration.
 *
 * SECURITY NOTE: the API keys below are DEMO values for a self-contained project.
 * In production, store hashed keys or use an IdP/OAuth, load them from env/vault,
 * and serve over TLS.
 */

return [
    'db_path' => getenv('WORKORDERS_DB') ?: dirname(__DIR__) . '/data/workorders.sqlite',

    // Bearer API keys -> role. 'admin' = read+write, 'viewer' = read-only.
    'api_keys' => [
        (getenv('WORKORDERS_ADMIN_KEY') ?: 'admin-key-123')  => ['client' => 'ops-admin', 'role' => 'admin'],
        (getenv('WORKORDERS_VIEWER_KEY') ?: 'viewer-key-abc') => ['client' => 'dashboard', 'role' => 'viewer'],
    ],

    'pagination' => ['default_per_page' => 20, 'max_per_page' => 100],
];
