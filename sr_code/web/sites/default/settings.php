<?php

/**
 * Database configuration for Drupal.
 */

// Database connection settings
$databases['default']['default'] = [
  'database' => 'stayrelive',
  'username' => 'stayrelive',
  'password' => 'stayrelive',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_unicode_ci',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'isolation_level' => 'READ COMMITTED',
];

/**
 * Salt for one-time login links, cancel links, form tokens, etc.
 */
$settings['hash_salt'] = 'stayrelive_hash_salt_key_2026';

/**
 * Access control for update.php script.
 */
$settings['update_free_access'] = FALSE;

/**
 * Trusted host configuration.
 */
$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^stayrelive\.test$',
];

/**
 * Load local development settings if they exist.
 */
if (file_exists(__DIR__ . '/settings.local.php')) {
  include __DIR__ . '/settings.local.php';
}

/**
 * Load services configuration.
 */
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';

// SHOW ERRORS
$config['system.logging']['error_level'] = 'verbose';





