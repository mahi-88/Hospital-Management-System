<?php

namespace Leantime\Domain\Auth\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Core\Environment\Environment;
use Illuminate\Support\Facades\Cache;
use PDO;

/**
 * PixelForge Nexus RBAC Service
 * 
 * Comprehensive Role-Based Access Control system for managing
 * user permissions and role assignments.
 */
class RBACService
{
    private DbCore $db;
    private Environment $config;
    private array $userPermissionsCache = [];
    private array $rolePermissionsCache = [];

    public function __construct(DbCore $db, Environment $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Check if user has a specific permission
     */
    public function userHasPermission(int $userId, string $permissionName, ?int $projectId = null): bool
    {
        $cacheKey = "user_permission_{$userId}_{$permissionName}" . ($projectId ? "_{$projectId}" : '');
        
        if (isset($this->userPermissionsCache[$cacheKey])) {
            return $this->userPermissionsCache[$cacheKey];
        }

        $sql = "
            SELECT COUNT(*) as count
            FROM zp_user_roles ur
            JOIN zp_role_permissions rp ON ur.role_id = rp.role_id
            JOIN zp_permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id 
            AND p.name = :permission_name
            AND ur.is_active = 1
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            AND (ur.project_id IS NULL OR ur.project_id = :project_id OR :project_id IS NULL)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':permission_name', $permissionName, PDO::PARAM_STR);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $hasPermission = $result['count'] > 0;
        $this->userPermissionsCache[$cacheKey] = $hasPermission;

        return $hasPermission;
    }

    /**
     * Check if user has any of the specified permissions
     */
    public function userHasAnyPermission(int $userId, array $permissions, ?int $projectId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userHasPermission($userId, $permission, $projectId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all specified permissions
     */
    public function userHasAllPermissions(int $userId, array $permissions, ?int $projectId = null): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->userHasPermission($userId, $permission, $projectId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions(int $userId, ?int $projectId = null): array
    {
        $cacheKey = "user_all_permissions_{$userId}" . ($projectId ? "_{$projectId}" : '');
        
        if (isset($this->userPermissionsCache[$cacheKey])) {
            return $this->userPermissionsCache[$cacheKey];
        }

        $sql = "
            SELECT DISTINCT p.name, p.display_name, p.description, p.category
            FROM zp_user_roles ur
            JOIN zp_role_permissions rp ON ur.role_id = rp.role_id
            JOIN zp_permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id 
            AND ur.is_active = 1
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            AND (ur.project_id IS NULL OR ur.project_id = :project_id OR :project_id IS NULL)
            ORDER BY p.category, p.display_name
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $this->userPermissionsCache[$cacheKey] = $permissions;
        return $permissions;
    }

    /**
     * Get all roles for a user
     */
    public function getUserRoles(int $userId, ?int $projectId = null): array
    {
        $sql = "
            SELECT r.id, r.name, r.display_name, r.description, ur.project_id, ur.assigned_at, ur.expires_at
            FROM zp_user_roles ur
            JOIN zp_roles r ON ur.role_id = r.id
            WHERE ur.user_id = :user_id 
            AND ur.is_active = 1
            AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            AND (ur.project_id IS NULL OR ur.project_id = :project_id OR :project_id IS NULL)
            ORDER BY r.display_name
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $roles;
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(int $userId, int $roleId, ?int $projectId = null, ?int $assignedBy = null, ?\DateTime $expiresAt = null): bool
    {
        // Check if assignment already exists
        $existsSql = "
            SELECT id FROM zp_user_roles 
            WHERE user_id = :user_id AND role_id = :role_id 
            AND (project_id = :project_id OR (project_id IS NULL AND :project_id IS NULL))
            AND is_active = 1
        ";

        $stmt = $this->db->database->prepare($existsSql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            $stmt->closeCursor();
            return false; // Assignment already exists
        }
        $stmt->closeCursor();

        // Create new assignment
        $insertSql = "
            INSERT INTO zp_user_roles (user_id, role_id, project_id, assigned_by, assigned_at, expires_at)
            VALUES (:user_id, :role_id, :project_id, :assigned_by, NOW(), :expires_at)
        ";

        $stmt = $this->db->database->prepare($insertSql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':assigned_by', $assignedBy, PDO::PARAM_INT);
        $stmt->bindValue(':expires_at', $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null, PDO::PARAM_STR);
        
        $result = $stmt->execute();
        $stmt->closeCursor();

        // Clear cache
        $this->clearUserPermissionCache($userId);

        return $result;
    }

    /**
     * Remove role from user
     */
    public function removeRoleFromUser(int $userId, int $roleId, ?int $projectId = null): bool
    {
        $sql = "
            UPDATE zp_user_roles 
            SET is_active = 0 
            WHERE user_id = :user_id AND role_id = :role_id 
            AND (project_id = :project_id OR (project_id IS NULL AND :project_id IS NULL))
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        $stmt->closeCursor();

        // Clear cache
        $this->clearUserPermissionCache($userId);

        return $result;
    }

    /**
     * Get all available roles
     */
    public function getAllRoles(): array
    {
        $sql = "SELECT id, name, display_name, description FROM zp_roles ORDER BY display_name";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $roles;
    }

    /**
     * Get all available permissions
     */
    public function getAllPermissions(): array
    {
        $sql = "SELECT id, name, display_name, description, category FROM zp_permissions ORDER BY category, display_name";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $permissions;
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions(int $roleId): array
    {
        if (isset($this->rolePermissionsCache[$roleId])) {
            return $this->rolePermissionsCache[$roleId];
        }

        $sql = "
            SELECT p.id, p.name, p.display_name, p.description, p.category
            FROM zp_role_permissions rp
            JOIN zp_permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            ORDER BY p.category, p.display_name
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $this->rolePermissionsCache[$roleId] = $permissions;
        return $permissions;
    }

    /**
     * Clear user permission cache
     */
    private function clearUserPermissionCache(int $userId): void
    {
        foreach (array_keys($this->userPermissionsCache) as $key) {
            if (strpos($key, "user_permission_{$userId}_") === 0 || 
                strpos($key, "user_all_permissions_{$userId}") === 0) {
                unset($this->userPermissionsCache[$key]);
            }
        }
    }

    /**
     * Check if user is super admin (has manage_system permission)
     */
    public function isSuperAdmin(int $userId): bool
    {
        return $this->userHasPermission($userId, 'manage_system');
    }

    /**
     * Check if user can manage a specific project
     */
    public function canManageProject(int $userId, int $projectId): bool
    {
        return $this->userHasAnyPermission($userId, [
            'manage_system', 
            'edit_project', 
            'manage_project_team'
        ], $projectId);
    }
}
