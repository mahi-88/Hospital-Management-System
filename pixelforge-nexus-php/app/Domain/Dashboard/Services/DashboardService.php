<?php

namespace Leantime\Domain\Dashboard\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Auth\Services\RBACService;
use Leantime\Domain\Tickets\Services\KanbanService;
use PDO;

/**
 * PixelForge Nexus Dashboard Service
 * 
 * Provides role-specific dashboard data and widgets
 */
class DashboardService
{
    private DbCore $db;
    private RBACService $rbacService;
    private KanbanService $kanbanService;

    public function __construct(DbCore $db, RBACService $rbacService, KanbanService $kanbanService)
    {
        $this->db = $db;
        $this->rbacService = $rbacService;
        $this->kanbanService = $kanbanService;
    }

    /**
     * Get role-specific dashboard data
     */
    public function getDashboardData(int $userId): array
    {
        $userRoles = $this->rbacService->getUserRoles($userId);
        $primaryRole = $this->getPrimaryRole($userRoles);

        $dashboardData = [
            'user_id' => $userId,
            'primary_role' => $primaryRole,
            'widgets' => [],
            'quick_actions' => [],
            'recent_activity' => $this->getRecentActivity($userId),
            'notifications' => $this->getNotifications($userId)
        ];

        // Load role-specific widgets and data
        switch ($primaryRole) {
            case 'super_admin':
                $dashboardData = array_merge($dashboardData, $this->getSuperAdminDashboard($userId));
                break;
            case 'project_admin':
                $dashboardData = array_merge($dashboardData, $this->getProjectAdminDashboard($userId));
                break;
            case 'developer':
                $dashboardData = array_merge($dashboardData, $this->getDeveloperDashboard($userId));
                break;
            case 'designer':
                $dashboardData = array_merge($dashboardData, $this->getDesignerDashboard($userId));
                break;
            case 'qa_engineer':
                $dashboardData = array_merge($dashboardData, $this->getQAEngineerDashboard($userId));
                break;
            case 'client':
                $dashboardData = array_merge($dashboardData, $this->getClientDashboard($userId));
                break;
            default:
                $dashboardData = array_merge($dashboardData, $this->getGuestDashboard($userId));
        }

        return $dashboardData;
    }

    /**
     * Super Admin Dashboard
     */
    private function getSuperAdminDashboard(int $userId): array
    {
        return [
            'widgets' => [
                'system_overview' => $this->getSystemOverview(),
                'user_management' => $this->getUserManagementStats(),
                'project_overview' => $this->getProjectOverview(),
                'security_alerts' => $this->getSecurityAlerts(),
                'system_health' => $this->getSystemHealth()
            ],
            'quick_actions' => [
                ['title' => 'Create Project', 'url' => '/projects/create', 'icon' => 'fa-plus'],
                ['title' => 'Manage Users', 'url' => '/users', 'icon' => 'fa-users'],
                ['title' => 'Role Management', 'url' => '/auth/roleManagement', 'icon' => 'fa-shield'],
                ['title' => 'System Settings', 'url' => '/settings', 'icon' => 'fa-cog'],
                ['title' => 'View Logs', 'url' => '/logs', 'icon' => 'fa-file-text']
            ]
        ];
    }

    /**
     * Project Admin Dashboard
     */
    private function getProjectAdminDashboard(int $userId): array
    {
        $managedProjects = $this->getManagedProjects($userId);
        
        return [
            'widgets' => [
                'managed_projects' => $managedProjects,
                'team_performance' => $this->getTeamPerformance($userId),
                'project_progress' => $this->getProjectProgress($userId),
                'pending_approvals' => $this->getPendingApprovals($userId),
                'resource_allocation' => $this->getResourceAllocation($userId)
            ],
            'quick_actions' => [
                ['title' => 'Create Project', 'url' => '/projects/create', 'icon' => 'fa-plus'],
                ['title' => 'Invite Team Member', 'url' => '/projects/invite', 'icon' => 'fa-user-plus'],
                ['title' => 'View Reports', 'url' => '/reports', 'icon' => 'fa-chart-bar'],
                ['title' => 'Manage Sprints', 'url' => '/sprints', 'icon' => 'fa-calendar']
            ]
        ];
    }

    /**
     * Developer Dashboard
     */
    private function getDeveloperDashboard(int $userId): array
    {
        $assignedTickets = $this->kanbanService->getUserTickets($userId);
        
        return [
            'widgets' => [
                'my_tasks' => $assignedTickets,
                'sprint_progress' => $this->getCurrentSprintProgress($userId),
                'code_reviews' => $this->getCodeReviews($userId),
                'recent_commits' => $this->getRecentCommits($userId),
                'bug_reports' => $this->getAssignedBugs($userId)
            ],
            'quick_actions' => [
                ['title' => 'Create Task', 'url' => '/tickets/create', 'icon' => 'fa-plus'],
                ['title' => 'View Kanban', 'url' => '/kanban', 'icon' => 'fa-columns'],
                ['title' => 'Upload Code', 'url' => '/files/upload', 'icon' => 'fa-upload'],
                ['title' => 'View Documentation', 'url' => '/docs', 'icon' => 'fa-book']
            ]
        ];
    }

    /**
     * Designer Dashboard
     */
    private function getDesignerDashboard(int $userId): array
    {
        return [
            'widgets' => [
                'design_tasks' => $this->getDesignTasks($userId),
                'asset_library' => $this->getAssetLibrary($userId),
                'design_reviews' => $this->getDesignReviews($userId),
                'creative_briefs' => $this->getCreativeBriefs($userId),
                'inspiration_board' => $this->getInspirationBoard($userId)
            ],
            'quick_actions' => [
                ['title' => 'Upload Asset', 'url' => '/assets/upload', 'icon' => 'fa-upload'],
                ['title' => 'Create Design Task', 'url' => '/tickets/create?type=design', 'icon' => 'fa-paint-brush'],
                ['title' => 'View Asset Library', 'url' => '/assets', 'icon' => 'fa-images'],
                ['title' => 'Design Reviews', 'url' => '/reviews', 'icon' => 'fa-eye']
            ]
        ];
    }

    /**
     * QA Engineer Dashboard
     */
    private function getQAEngineerDashboard(int $userId): array
    {
        return [
            'widgets' => [
                'test_cases' => $this->getTestCases($userId),
                'bug_reports' => $this->getBugReports($userId),
                'test_execution' => $this->getTestExecution($userId),
                'quality_metrics' => $this->getQualityMetrics($userId),
                'release_readiness' => $this->getReleaseReadiness($userId)
            ],
            'quick_actions' => [
                ['title' => 'Create Bug Report', 'url' => '/tickets/create?type=bug', 'icon' => 'fa-bug'],
                ['title' => 'Run Test Suite', 'url' => '/testing/run', 'icon' => 'fa-play'],
                ['title' => 'View Test Results', 'url' => '/testing/results', 'icon' => 'fa-chart-line'],
                ['title' => 'Quality Dashboard', 'url' => '/quality', 'icon' => 'fa-shield-alt']
            ]
        ];
    }

    /**
     * Client Dashboard
     */
    private function getClientDashboard(int $userId): array
    {
        return [
            'widgets' => [
                'project_status' => $this->getClientProjectStatus($userId),
                'deliverables' => $this->getClientDeliverables($userId),
                'milestones' => $this->getClientMilestones($userId),
                'feedback_requests' => $this->getFeedbackRequests($userId),
                'approved_assets' => $this->getApprovedAssets($userId)
            ],
            'quick_actions' => [
                ['title' => 'View Projects', 'url' => '/client/projects', 'icon' => 'fa-folder'],
                ['title' => 'Provide Feedback', 'url' => '/client/feedback', 'icon' => 'fa-comment'],
                ['title' => 'Download Assets', 'url' => '/client/assets', 'icon' => 'fa-download'],
                ['title' => 'View Reports', 'url' => '/client/reports', 'icon' => 'fa-chart-bar']
            ]
        ];
    }

    /**
     * Guest Dashboard
     */
    private function getGuestDashboard(int $userId): array
    {
        return [
            'widgets' => [
                'available_projects' => $this->getAvailableProjects($userId),
                'public_assets' => $this->getPublicAssets($userId),
                'getting_started' => $this->getGettingStarted()
            ],
            'quick_actions' => [
                ['title' => 'Browse Projects', 'url' => '/projects/browse', 'icon' => 'fa-search'],
                ['title' => 'Contact Support', 'url' => '/support', 'icon' => 'fa-question-circle']
            ]
        ];
    }

    /**
     * Get system overview for super admin
     */
    private function getSystemOverview(): array
    {
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM zp_user WHERE status = 'A') as active_users,
                (SELECT COUNT(*) FROM zp_projects WHERE state = 1) as active_projects,
                (SELECT COUNT(*) FROM zp_tickets WHERE status = 1) as open_tickets,
                (SELECT COUNT(*) FROM zp_project_invitations WHERE status = 'pending') as pending_invitations
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: [];
    }

    /**
     * Get user management stats
     */
    private function getUserManagementStats(): array
    {
        $sql = "
            SELECT 
                ur.role_id,
                r.display_name as role_name,
                COUNT(*) as user_count
            FROM zp_user_roles ur
            JOIN zp_roles r ON ur.role_id = r.id
            WHERE ur.is_active = 1
            GROUP BY ur.role_id, r.display_name
            ORDER BY user_count DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $results;
    }

    /**
     * Get project overview
     */
    private function getProjectOverview(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_projects,
                SUM(CASE WHEN state = 1 THEN 1 ELSE 0 END) as active_projects,
                SUM(CASE WHEN state = 2 THEN 1 ELSE 0 END) as completed_projects,
                SUM(CASE WHEN state = 0 THEN 1 ELSE 0 END) as archived_projects
            FROM zp_projects
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: [];
    }

    /**
     * Get recent activity for user
     */
    private function getRecentActivity(int $userId, int $limit = 10): array
    {
        $sql = "
            SELECT 
                ta.*,
                p.name as project_name,
                u.firstname,
                u.lastname
            FROM zp_team_activity ta
            JOIN zp_projects p ON ta.project_id = p.id
            JOIN zp_user u ON ta.user_id = u.id
            WHERE ta.user_id = :user_id OR ta.project_id IN (
                SELECT DISTINCT ur.project_id 
                FROM zp_user_roles ur 
                WHERE ur.user_id = :user_id AND ur.is_active = 1
            )
            ORDER BY ta.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $results;
    }

    /**
     * Get notifications for user
     */
    private function getNotifications(int $userId): array
    {
        // Get pending invitations
        $invitations = [];
        $user = $this->getUserById($userId);
        if ($user) {
            $sql = "
                SELECT pi.*, p.name as project_name, r.display_name as role_name
                FROM zp_project_invitations pi
                JOIN zp_projects p ON pi.project_id = p.id
                JOIN zp_roles r ON pi.role_id = r.id
                WHERE pi.email = :email AND pi.status = 'pending' AND pi.expires_at > NOW()
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':email', $user['username'], PDO::PARAM_STR);
            $stmt->execute();
            $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
        }

        return [
            'invitations' => $invitations,
            'unread_count' => count($invitations)
        ];
    }

    /**
     * Get primary role for user
     */
    private function getPrimaryRole(array $userRoles): string
    {
        if (empty($userRoles)) {
            return 'guest';
        }

        // Priority order for roles
        $rolePriority = [
            'super_admin' => 7,
            'project_admin' => 6,
            'qa_engineer' => 5,
            'designer' => 4,
            'developer' => 3,
            'client' => 2,
            'guest' => 1
        ];

        $highestPriority = 0;
        $primaryRole = 'guest';

        foreach ($userRoles as $role) {
            $priority = $rolePriority[$role['name']] ?? 0;
            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $primaryRole = $role['name'];
            }
        }

        return $primaryRole;
    }

    /**
     * Get user by ID
     */
    private function getUserById(int $userId): ?array
    {
        $sql = "SELECT * FROM zp_user WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $user ?: null;
    }

    // Placeholder methods for specific dashboard widgets
    // These would be implemented based on specific requirements

    private function getManagedProjects(int $userId): array { return []; }
    private function getTeamPerformance(int $userId): array { return []; }
    private function getProjectProgress(int $userId): array { return []; }
    private function getPendingApprovals(int $userId): array { return []; }
    private function getResourceAllocation(int $userId): array { return []; }
    private function getCurrentSprintProgress(int $userId): array { return []; }
    private function getCodeReviews(int $userId): array { return []; }
    private function getRecentCommits(int $userId): array { return []; }
    private function getAssignedBugs(int $userId): array { return []; }
    private function getDesignTasks(int $userId): array { return []; }
    private function getAssetLibrary(int $userId): array { return []; }
    private function getDesignReviews(int $userId): array { return []; }
    private function getCreativeBriefs(int $userId): array { return []; }
    private function getInspirationBoard(int $userId): array { return []; }
    private function getTestCases(int $userId): array { return []; }
    private function getBugReports(int $userId): array { return []; }
    private function getTestExecution(int $userId): array { return []; }
    private function getQualityMetrics(int $userId): array { return []; }
    private function getReleaseReadiness(int $userId): array { return []; }
    private function getClientProjectStatus(int $userId): array { return []; }
    private function getClientDeliverables(int $userId): array { return []; }
    private function getClientMilestones(int $userId): array { return []; }
    private function getFeedbackRequests(int $userId): array { return []; }
    private function getApprovedAssets(int $userId): array { return []; }
    private function getAvailableProjects(int $userId): array { return []; }
    private function getPublicAssets(int $userId): array { return []; }
    private function getGettingStarted(): array { return []; }
    private function getSecurityAlerts(): array { return []; }
    private function getSystemHealth(): array { return []; }
}
