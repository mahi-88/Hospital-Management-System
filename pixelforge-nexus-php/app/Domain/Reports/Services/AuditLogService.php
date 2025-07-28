<?php

namespace Leantime\Domain\Reports\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Auth\Services\RBACService;
use PDO;
use Exception;

/**
 * PixelForge Nexus Audit Log Service
 * 
 * Comprehensive audit logging for security, compliance, and activity tracking
 */
class AuditLogService
{
    private DbCore $db;
    private RBACService $rbacService;

    public function __construct(DbCore $db, RBACService $rbacService)
    {
        $this->db = $db;
        $this->rbacService = $rbacService;
    }

    /**
     * Log a system action
     */
    public function logAction(
        ?int $userId,
        string $actionType,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $severity = 'medium',
        ?string $category = null,
        ?int $projectId = null,
        ?array $metadata = null
    ): bool {
        try {
            $sql = "
                INSERT INTO zp_audit_logs (
                    user_id, session_id, action_type, entity_type, entity_id, description,
                    old_values, new_values, ip_address, user_agent, request_method, request_url,
                    severity, category, project_id, metadata
                ) VALUES (
                    :user_id, :session_id, :action_type, :entity_type, :entity_id, :description,
                    :old_values, :new_values, :ip_address, :user_agent, :request_method, :request_url,
                    :severity, :category, :project_id, :metadata
                )
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':session_id', session_id(), PDO::PARAM_STR);
            $stmt->bindValue(':action_type', $actionType, PDO::PARAM_STR);
            $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':old_values', $oldValues ? json_encode($oldValues) : null, PDO::PARAM_STR);
            $stmt->bindValue(':new_values', $newValues ? json_encode($newValues) : null, PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $this->getClientIpAddress(), PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
            $stmt->bindValue(':request_method', $_SERVER['REQUEST_METHOD'] ?? 'unknown', PDO::PARAM_STR);
            $stmt->bindValue(':request_url', $this->getCurrentUrl(), PDO::PARAM_STR);
            $stmt->bindValue(':severity', $severity, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
            $stmt->bindValue(':metadata', $metadata ? json_encode($metadata) : null, PDO::PARAM_STR);

            $result = $stmt->execute();
            $stmt->closeCursor();

            return $result;

        } catch (Exception $e) {
            // Log to error log but don't throw exception to avoid breaking main functionality
            error_log("Audit log failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit logs with filtering and pagination
     */
    public function getAuditLogs(
        int $userId,
        ?int $targetUserId = null,
        ?string $actionType = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $category = null,
        ?int $projectId = null,
        ?string $severity = null,
        ?string $startDate = null,
        ?string $endDate = null,
        int $page = 1,
        int $limit = 50
    ): array {
        // Check permissions
        if (!$this->rbacService->userHasPermission($userId, 'view_audit_logs')) {
            return ['logs' => [], 'total' => 0, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        $whereConditions = ['1=1'];
        $params = [];

        // Apply filters
        if ($targetUserId) {
            $whereConditions[] = 'al.user_id = :target_user_id';
            $params[':target_user_id'] = $targetUserId;
        }

        if ($actionType) {
            $whereConditions[] = 'al.action_type = :action_type';
            $params[':action_type'] = $actionType;
        }

        if ($entityType) {
            $whereConditions[] = 'al.entity_type = :entity_type';
            $params[':entity_type'] = $entityType;
        }

        if ($entityId) {
            $whereConditions[] = 'al.entity_id = :entity_id';
            $params[':entity_id'] = $entityId;
        }

        if ($category) {
            $whereConditions[] = 'al.category = :category';
            $params[':category'] = $category;
        }

        if ($projectId) {
            $whereConditions[] = 'al.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }

        if ($severity) {
            $whereConditions[] = 'al.severity = :severity';
            $params[':severity'] = $severity;
        }

        if ($startDate) {
            $whereConditions[] = 'al.created_at >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate) {
            $whereConditions[] = 'al.created_at <= :end_date';
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        // Check if user can only see their own project logs
        $userRoles = $this->rbacService->getUserRoles($userId);
        $isSuperAdmin = !empty(array_filter($userRoles, fn($role) => $role['name'] === 'super_admin'));
        
        if (!$isSuperAdmin) {
            // Limit to projects user has access to
            $userProjects = $this->getUserAccessibleProjects($userId);
            if (!empty($userProjects)) {
                $projectPlaceholders = implode(',', array_fill(0, count($userProjects), '?'));
                $whereConditions[] = "(al.project_id IN ({$projectPlaceholders}) OR al.project_id IS NULL)";
                $params = array_merge($params, $userProjects);
            }
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM zp_audit_logs al WHERE {$whereClause}";
        $stmt = $this->db->database->prepare($countSql);
        $this->bindParameters($stmt, $params);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt->closeCursor();

        // Get logs
        $sql = "
            SELECT 
                al.*,
                u.firstname,
                u.lastname,
                u.username,
                p.name as project_name
            FROM zp_audit_logs al
            LEFT JOIN zp_user u ON al.user_id = u.id
            LEFT JOIN zp_projects p ON al.project_id = p.id
            WHERE {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->database->prepare($sql);
        $this->bindParameters($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process logs
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
            $log['metadata'] = $log['metadata'] ? json_decode($log['metadata'], true) : null;
            $log['user_display_name'] = trim(($log['firstname'] ?? '') . ' ' . ($log['lastname'] ?? '')) ?: $log['username'] ?? 'System';
        }

        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }

    /**
     * Get logs by entity
     */
    public function getLogsByEntity(string $entityType, int $entityId, int $userId, int $limit = 20): array
    {
        if (!$this->rbacService->userHasPermission($userId, 'view_audit_logs')) {
            return [];
        }

        $sql = "
            SELECT 
                al.*,
                u.firstname,
                u.lastname,
                u.username
            FROM zp_audit_logs al
            LEFT JOIN zp_user u ON al.user_id = u.id
            WHERE al.entity_type = :entity_type AND al.entity_id = :entity_id
            ORDER BY al.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process logs
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
            $log['metadata'] = $log['metadata'] ? json_decode($log['metadata'], true) : null;
            $log['user_display_name'] = trim(($log['firstname'] ?? '') . ' ' . ($log['lastname'] ?? '')) ?: $log['username'] ?? 'System';
        }

        return $logs;
    }

    /**
     * Get logs by user
     */
    public function getLogsByUser(int $targetUserId, int $requestingUserId, int $limit = 50): array
    {
        if (!$this->rbacService->userHasPermission($requestingUserId, 'view_audit_logs')) {
            return [];
        }

        $sql = "
            SELECT 
                al.*,
                p.name as project_name
            FROM zp_audit_logs al
            LEFT JOIN zp_projects p ON al.project_id = p.id
            WHERE al.user_id = :user_id
            ORDER BY al.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process logs
        foreach ($logs as &$log) {
            $log['old_values'] = $log['old_values'] ? json_decode($log['old_values'], true) : null;
            $log['new_values'] = $log['new_values'] ? json_decode($log['new_values'], true) : null;
            $log['metadata'] = $log['metadata'] ? json_decode($log['metadata'], true) : null;
        }

        return $logs;
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics(int $userId, ?int $projectId = null, int $days = 30): array
    {
        if (!$this->rbacService->userHasPermission($userId, 'view_audit_logs')) {
            return [];
        }

        $whereCondition = "WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        $params = [':days' => $days];

        if ($projectId) {
            $whereCondition .= " AND al.project_id = :project_id";
            $params[':project_id'] = $projectId;
        }

        $sql = "
            SELECT 
                al.action_type,
                al.category,
                al.severity,
                COUNT(*) as count,
                COUNT(DISTINCT al.user_id) as unique_users,
                DATE(al.created_at) as date
            FROM zp_audit_logs al
            {$whereCondition}
            GROUP BY al.action_type, al.category, al.severity, DATE(al.created_at)
            ORDER BY al.created_at DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $this->processAuditStatistics($stats);
    }

    /**
     * Clean up old audit logs
     */
    public function cleanupOldLogs(int $retentionDays = 365): int
    {
        $sql = "
            DELETE FROM zp_audit_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)
            AND severity NOT IN ('high', 'critical')
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':retention_days', $retentionDays, PDO::PARAM_INT);
        $stmt->execute();
        $deletedCount = $stmt->rowCount();
        $stmt->closeCursor();

        // Log the cleanup action
        $this->logAction(
            null,
            'audit_cleanup',
            "Cleaned up {$deletedCount} audit log entries older than {$retentionDays} days",
            'system',
            null,
            null,
            null,
            'low',
            'system',
            null,
            ['deleted_count' => $deletedCount, 'retention_days' => $retentionDays]
        );

        return $deletedCount;
    }

    /**
     * Export audit logs to various formats
     */
    public function exportAuditLogs(
        int $userId,
        string $format = 'csv',
        ?array $filters = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        if (!$this->rbacService->userHasPermission($userId, 'export_audit_logs')) {
            return ['success' => false, 'message' => 'Insufficient permissions to export audit logs'];
        }

        // Get filtered logs
        $logsData = $this->getAuditLogs(
            $userId,
            $filters['user_id'] ?? null,
            $filters['action_type'] ?? null,
            $filters['entity_type'] ?? null,
            $filters['entity_id'] ?? null,
            $filters['category'] ?? null,
            $filters['project_id'] ?? null,
            $filters['severity'] ?? null,
            $startDate,
            $endDate,
            1,
            10000 // Large limit for export
        );

        if (empty($logsData['logs'])) {
            return ['success' => false, 'message' => 'No audit logs found for the specified criteria'];
        }

        // Generate export file
        $fileName = 'audit_logs_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filePath = $this->generateExportFile($logsData['logs'], $format, $fileName);

        if (!$filePath) {
            return ['success' => false, 'message' => 'Failed to generate export file'];
        }

        // Log the export action
        $this->logAction(
            $userId,
            'audit_export',
            "Exported {$logsData['total']} audit log entries to {$format} format",
            'audit_logs',
            null,
            null,
            null,
            'medium',
            'reporting',
            null,
            ['export_format' => $format, 'record_count' => $logsData['total'], 'filters' => $filters]
        );

        return [
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'record_count' => $logsData['total']
        ];
    }

    // Private helper methods
    private function getClientIpAddress(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . $host . $uri;
    }

    private function getUserAccessibleProjects(int $userId): array
    {
        $sql = "
            SELECT DISTINCT ur.project_id 
            FROM zp_user_roles ur 
            WHERE ur.user_id = :user_id AND ur.is_active = 1 AND ur.project_id IS NOT NULL
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        return $projects;
    }

    private function bindParameters(PDO $stmt, array $params): void
    {
        $index = 1;
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $stmt->bindValue($key, $value);
            } else {
                $stmt->bindValue($index++, $value);
            }
        }
    }

    private function processAuditStatistics(array $stats): array
    {
        $processed = [
            'by_action' => [],
            'by_category' => [],
            'by_severity' => [],
            'by_date' => [],
            'total_actions' => 0,
            'unique_users' => 0
        ];

        $uniqueUsers = [];

        foreach ($stats as $stat) {
            // By action type
            if (!isset($processed['by_action'][$stat['action_type']])) {
                $processed['by_action'][$stat['action_type']] = 0;
            }
            $processed['by_action'][$stat['action_type']] += $stat['count'];

            // By category
            if ($stat['category']) {
                if (!isset($processed['by_category'][$stat['category']])) {
                    $processed['by_category'][$stat['category']] = 0;
                }
                $processed['by_category'][$stat['category']] += $stat['count'];
            }

            // By severity
            if (!isset($processed['by_severity'][$stat['severity']])) {
                $processed['by_severity'][$stat['severity']] = 0;
            }
            $processed['by_severity'][$stat['severity']] += $stat['count'];

            // By date
            if (!isset($processed['by_date'][$stat['date']])) {
                $processed['by_date'][$stat['date']] = 0;
            }
            $processed['by_date'][$stat['date']] += $stat['count'];

            $processed['total_actions'] += $stat['count'];
            $uniqueUsers[$stat['unique_users']] = true;
        }

        $processed['unique_users'] = count($uniqueUsers);

        return $processed;
    }
}
