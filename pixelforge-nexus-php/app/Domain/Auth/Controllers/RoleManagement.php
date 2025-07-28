<?php

namespace Leantime\Domain\Auth\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller as FrontcontrollerCore;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Auth\Services\RBACService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * PixelForge Nexus Role Management Controller
 * 
 * Handles role and permission management for the RBAC system
 */
class RoleManagement extends Controller
{
    private RBACService $rbacService;
    private UserRepository $userRepository;

    public function __construct(RBACService $rbacService, UserRepository $userRepository)
    {
        $this->rbacService = $rbacService;
        $this->userRepository = $userRepository;
        
        // Ensure only super admins can access role management
        Auth::authOrRedirect('manage_system');
    }

    /**
     * Display roles overview
     */
    public function index(): void
    {
        $this->tpl->assign('roles', $this->rbacService->getAllRoles());
        $this->tpl->assign('permissions', $this->rbacService->getAllPermissions());
        $this->tpl->display('auth.roleManagement.index');
    }

    /**
     * Display role details and permissions
     */
    public function showRole(): void
    {
        $roleId = (int) ($_GET['roleId'] ?? 0);
        
        if (!$roleId) {
            $this->tpl->setNotification('Invalid role ID', 'error');
            return $this->index();
        }

        $roles = $this->rbacService->getAllRoles();
        $role = array_filter($roles, fn($r) => $r['id'] == $roleId);
        
        if (empty($role)) {
            $this->tpl->setNotification('Role not found', 'error');
            return $this->index();
        }

        $role = reset($role);
        $rolePermissions = $this->rbacService->getRolePermissions($roleId);
        $allPermissions = $this->rbacService->getAllPermissions();

        // Group permissions by category
        $permissionsByCategory = [];
        foreach ($allPermissions as $permission) {
            $permissionsByCategory[$permission['category']][] = $permission;
        }

        // Mark which permissions this role has
        $rolePermissionIds = array_column($rolePermissions, 'id');
        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $key => $permission) {
                $permissionsByCategory[$category][$key]['hasPermission'] = in_array($permission['id'], $rolePermissionIds);
            }
        }

        $this->tpl->assign('role', $role);
        $this->tpl->assign('rolePermissions', $rolePermissions);
        $this->tpl->assign('permissionsByCategory', $permissionsByCategory);
        $this->tpl->display('auth.roleManagement.showRole');
    }

    /**
     * Display user role assignments
     */
    public function userRoles(): void
    {
        $userId = (int) ($_GET['userId'] ?? 0);
        
        if (!$userId) {
            // Show all users with their roles
            $users = $this->userRepository->getAll();
            $usersWithRoles = [];
            
            foreach ($users as $user) {
                $userRoles = $this->rbacService->getUserRoles($user['id']);
                $usersWithRoles[] = [
                    'user' => $user,
                    'roles' => $userRoles
                ];
            }
            
            $this->tpl->assign('usersWithRoles', $usersWithRoles);
            $this->tpl->assign('allRoles', $this->rbacService->getAllRoles());
            $this->tpl->display('auth.roleManagement.userRoles');
        } else {
            // Show specific user's roles
            $user = $this->userRepository->getUser($userId);
            if (!$user) {
                $this->tpl->setNotification('User not found', 'error');
                return $this->userRoles();
            }

            $userRoles = $this->rbacService->getUserRoles($userId);
            $userPermissions = $this->rbacService->getUserPermissions($userId);
            $allRoles = $this->rbacService->getAllRoles();

            $this->tpl->assign('user', $user);
            $this->tpl->assign('userRoles', $userRoles);
            $this->tpl->assign('userPermissions', $userPermissions);
            $this->tpl->assign('allRoles', $allRoles);
            $this->tpl->display('auth.roleManagement.userRoleDetail');
        }
    }

    /**
     * Assign role to user
     */
    public function assignRole(): void
    {
        if ($_POST) {
            $userId = (int) ($_POST['userId'] ?? 0);
            $roleId = (int) ($_POST['roleId'] ?? 0);
            $projectId = !empty($_POST['projectId']) ? (int) $_POST['projectId'] : null;
            $expiresAt = !empty($_POST['expiresAt']) ? new \DateTime($_POST['expiresAt']) : null;
            
            if (!$userId || !$roleId) {
                $this->tpl->setNotification('Invalid user or role ID', 'error');
                return $this->userRoles();
            }

            $assignedBy = session('userdata.id');
            $success = $this->rbacService->assignRoleToUser($userId, $roleId, $projectId, $assignedBy, $expiresAt);
            
            if ($success) {
                $this->tpl->setNotification('Role assigned successfully', 'success');
            } else {
                $this->tpl->setNotification('Failed to assign role. Role may already be assigned.', 'error');
            }
            
            return $this->userRoles();
        }

        // Show assignment form
        $users = $this->userRepository->getAll();
        $roles = $this->rbacService->getAllRoles();
        
        $this->tpl->assign('users', $users);
        $this->tpl->assign('roles', $roles);
        $this->tpl->display('auth.roleManagement.assignRole');
    }

    /**
     * Remove role from user
     */
    public function removeRole(): void
    {
        if ($_POST) {
            $userId = (int) ($_POST['userId'] ?? 0);
            $roleId = (int) ($_POST['roleId'] ?? 0);
            $projectId = !empty($_POST['projectId']) ? (int) $_POST['projectId'] : null;
            
            if (!$userId || !$roleId) {
                $this->tpl->setNotification('Invalid user or role ID', 'error');
                return $this->userRoles();
            }

            $success = $this->rbacService->removeRoleFromUser($userId, $roleId, $projectId);
            
            if ($success) {
                $this->tpl->setNotification('Role removed successfully', 'success');
            } else {
                $this->tpl->setNotification('Failed to remove role', 'error');
            }
        }
        
        return $this->userRoles();
    }

    /**
     * Test permissions for debugging
     */
    public function testPermissions(): void
    {
        if (!$this->rbacService->isSuperAdmin(session('userdata.id'))) {
            $this->tpl->setNotification('Access denied', 'error');
            return $this->index();
        }

        $userId = (int) ($_GET['userId'] ?? session('userdata.id'));
        $projectId = !empty($_GET['projectId']) ? (int) $_GET['projectId'] : null;
        
        $user = $this->userRepository->getUser($userId);
        $userRoles = $this->rbacService->getUserRoles($userId, $projectId);
        $userPermissions = $this->rbacService->getUserPermissions($userId, $projectId);
        
        // Test common permissions
        $testPermissions = [
            'manage_system',
            'create_project',
            'edit_project',
            'upload_asset',
            'manage_users',
            'view_reports'
        ];
        
        $permissionTests = [];
        foreach ($testPermissions as $permission) {
            $permissionTests[$permission] = $this->rbacService->userHasPermission($userId, $permission, $projectId);
        }

        $this->tpl->assign('testUser', $user);
        $this->tpl->assign('testProjectId', $projectId);
        $this->tpl->assign('userRoles', $userRoles);
        $this->tpl->assign('userPermissions', $userPermissions);
        $this->tpl->assign('permissionTests', $permissionTests);
        $this->tpl->display('auth.roleManagement.testPermissions');
    }

    /**
     * Export role configuration
     */
    public function exportRoles(): void
    {
        $roles = $this->rbacService->getAllRoles();
        $permissions = $this->rbacService->getAllPermissions();
        
        $export = [
            'roles' => $roles,
            'permissions' => $permissions,
            'role_permissions' => []
        ];
        
        foreach ($roles as $role) {
            $rolePermissions = $this->rbacService->getRolePermissions($role['id']);
            $export['role_permissions'][$role['name']] = array_column($rolePermissions, 'name');
        }
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="pixelforge_roles_' . date('Y-m-d') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT);
        exit;
    }
}
