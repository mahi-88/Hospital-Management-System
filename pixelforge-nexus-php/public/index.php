<?php

/**
 * PixelForge Nexus - Secure Game Development Management System
 *
 * A comprehensive project management platform built for Creative SkillZ LLC
 * featuring enterprise-grade security, role-based access control, and
 * multi-factor authentication.
 *
 * @package PixelForge Nexus
 * @version 1.0.0
 * @author MAHI
 */

define('RESTRICTED', true);
define('LEANTIME_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/* Load Leantime helper functions before laravel */
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../vendor/autoload.php';

// Get the application once.
// Loads everything up once and then let's the bootloader manage it
$app = require_once __DIR__.'/../bootstrap/app.php';

// Pass app into leantime bootloader
\Leantime\Core\Bootloader::getInstance()->boot($app);
