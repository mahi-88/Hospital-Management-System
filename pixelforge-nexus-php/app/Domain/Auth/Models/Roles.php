<?php

namespace Leantime\Domain\Auth\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Events\DispatchesEvents;

/**
 * PixelForge Nexus Roles Model
 * Enhanced with RBAC system integration
 */
class Roles
{
    use DispatchesEvents;

    // Legacy role constants for backward compatibility
    public static string $readonly = 'readonly';
    public static string $commenter = 'commenter';
    public static string $editor = 'editor';
    public static string $manager = 'manager';
    public static string $admin = 'admin';
    public static string $owner = 'owner';

    // New PixelForge Nexus role constants
    public static string $superAdmin = 'super_admin';
    public static string $projectAdmin = 'project_admin';
    public static string $developer = 'developer';
    public static string $designer = 'designer';
    public static string $qaEngineer = 'qa_engineer';
    public static string $client = 'client';
    public static string $guest = 'guest';

    // Legacy role keys for backward compatibility
    private static array $roleKeys = [
        5 => 'readonly',      // prev: none
        10 => 'commenter',    // prev: client
        20 => 'editor',       // prev: developer
        30 => 'manager',      // prev: clientmanager
        40 => 'admin',        // prev: manager
        50 => 'owner',        // prev: admin
    ];

    // New PixelForge Nexus role hierarchy
    private static array $pixelForgeRoleKeys = [
        1 => 'guest',         // Minimal access
        2 => 'client',        // Read-only project access
        3 => 'developer',     // Development access
        4 => 'designer',      // Asset management
        5 => 'qa_engineer',   // QA and testing
        6 => 'project_admin', // Project management
        7 => 'super_admin',   // Full system access
    ];

    /**
     * @throws BindingResolutionException
     */
    private static function getFilteredRoles(): mixed
    {
        return self::dispatch_filter('available_roles', self::$roleKeys);
    }

    /**
     * @return false|mixed
     *
     * @throws BindingResolutionException
     */
    public static function getRoleString(mixed $key): mixed
    {
        return self::getFilteredRoles()[$key] ?? false;
    }

    /**
     * @throws BindingResolutionException
     */
    public static function getRoles(): mixed
    {
        return self::getFilteredRoles();
    }

    /**
     * Get PixelForge Nexus roles
     */
    public static function getPixelForgeRoles(): array
    {
        return self::$pixelForgeRoleKeys;
    }

    /**
     * Get role hierarchy level for PixelForge roles
     */
    public static function getPixelForgeRoleLevel(string $roleName): int
    {
        return array_search($roleName, self::$pixelForgeRoleKeys) ?: 0;
    }

    /**
     * Check if role is at least the specified level
     */
    public static function isAtLeastPixelForgeRole(string $userRole, string $requiredRole): bool
    {
        $userLevel = self::getPixelForgeRoleLevel($userRole);
        $requiredLevel = self::getPixelForgeRoleLevel($requiredRole);

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get all PixelForge role names
     */
    public static function getAllPixelForgeRoleNames(): array
    {
        return [
            self::$guest,
            self::$client,
            self::$developer,
            self::$designer,
            self::$qaEngineer,
            self::$projectAdmin,
            self::$superAdmin,
        ];
    }

    /**
     * Map legacy role to PixelForge role
     */
    public static function mapLegacyToPixelForge(string $legacyRole): string
    {
        $mapping = [
            'readonly' => self::$guest,
            'commenter' => self::$client,
            'editor' => self::$developer,
            'manager' => self::$projectAdmin,
            'admin' => self::$projectAdmin,
            'owner' => self::$superAdmin,
        ];

        return $mapping[$legacyRole] ?? self::$guest;
    }
}
