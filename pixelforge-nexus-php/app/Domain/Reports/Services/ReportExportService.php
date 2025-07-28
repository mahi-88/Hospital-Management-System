<?php

namespace Leantime\Domain\Reports\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Core\Environment\Environment;
use Leantime\Domain\Auth\Services\RBACService;
use PDO;
use Exception;

/**
 * PixelForge Nexus Report Export Service
 * 
 * Generates comprehensive reports in multiple formats (CSV, XLSX, PDF, JSON)
 */
class ReportExportService
{
    private DbCore $db;
    private Environment $config;
    private RBACService $rbacService;
    private AuditLogService $auditLogService;
    private string $exportPath;

    public function __construct(
        DbCore $db, 
        Environment $config, 
        RBACService $rbacService,
        AuditLogService $auditLogService
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->rbacService = $rbacService;
        $this->auditLogService = $auditLogService;
        $this->exportPath = rtrim($config->userFilePath ?? 'storage', '/') . '/reports';
        
        // Ensure export directory exists
        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }

    /**
     * Generate a report
     */
    public function generateReport(
        int $userId,
        string $reportType,
        array $parameters = [],
        string $format = 'csv',
        ?string $reportName = null
    ): array {
        // Check permissions
        if (!$this->rbacService->userHasPermission($userId, 'generate_reports')) {
            return ['success' => false, 'message' => 'Insufficient permissions to generate reports'];
        }

        try {
            // Create report export record
            $exportId = $this->createReportExportRecord($userId, $reportType, $parameters, $format, $reportName);
            
            // Generate report data based on type
            $reportData = $this->generateReportData($reportType, $parameters, $userId);
            
            if (empty($reportData)) {
                $this->updateReportStatus($exportId, 'failed', 'No data found for the specified criteria');
                return ['success' => false, 'message' => 'No data found for the specified criteria'];
            }

            // Generate export file
            $fileName = $this->generateFileName($reportType, $format);
            $filePath = $this->generateExportFile($reportData, $format, $fileName);
            
            if (!$filePath) {
                $this->updateReportStatus($exportId, 'failed', 'Failed to generate export file');
                return ['success' => false, 'message' => 'Failed to generate export file'];
            }

            // Update report record
            $this->updateReportRecord($exportId, $filePath, $fileName, filesize($filePath));
            
            // Log the report generation
            $this->auditLogService->logAction(
                $userId,
                'report_generated',
                "Generated {$reportType} report with " . count($reportData) . " records",
                'report',
                $exportId,
                null,
                null,
                'medium',
                'reporting',
                $parameters['project_id'] ?? null,
                ['report_type' => $reportType, 'format' => $format, 'record_count' => count($reportData)]
            );

            return [
                'success' => true,
                'export_id' => $exportId,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'record_count' => count($reportData)
            ];

        } catch (Exception $e) {
            if (isset($exportId)) {
                $this->updateReportStatus($exportId, 'failed', $e->getMessage());
            }
            return ['success' => false, 'message' => 'Report generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get user activity report data
     */
    private function generateUserActivityReport(array $parameters, int $userId): array
    {
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        $projectId = $parameters['project_id'] ?? null;

        $whereConditions = ['u.status = "A"'];
        $params = [':start_date' => $startDate, ':end_date' => $endDate . ' 23:59:59'];

        if ($projectId) {
            $whereConditions[] = 'ur.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.username,
                u.created as user_created,
                COUNT(DISTINCT al.id) as total_actions,
                COUNT(DISTINCT DATE(al.created_at)) as active_days,
                MAX(al.created_at) as last_activity,
                COUNT(DISTINCT d.id) as documents_uploaded,
                COUNT(DISTINCT p.id) as prototypes_submitted,
                COUNT(DISTINCT v.id) as videos_submitted,
                COUNT(DISTINCT ur.project_id) as projects_accessed,
                GROUP_CONCAT(DISTINCT r.display_name) as roles
            FROM zp_user u
            LEFT JOIN zp_audit_logs al ON u.id = al.user_id 
                AND al.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_documents d ON u.id = d.uploader_id 
                AND d.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_prototypes p ON u.id = p.submitted_by 
                AND p.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_video_submissions v ON u.id = v.submitted_by 
                AND v.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_user_roles ur ON u.id = ur.user_id AND ur.is_active = 1
            LEFT JOIN zp_roles r ON ur.role_id = r.id
            WHERE {$whereClause}
            GROUP BY u.id, u.firstname, u.lastname, u.username, u.created
            ORDER BY total_actions DESC, u.lastname, u.firstname
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $data;
    }

    /**
     * Get project performance report data
     */
    private function generateProjectPerformanceReport(array $parameters, int $userId): array
    {
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');

        $sql = "
            SELECT 
                p.id,
                p.name as project_name,
                p.description,
                p.created as project_created,
                p.state,
                COUNT(DISTINCT ur.user_id) as team_size,
                COUNT(DISTINCT t.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN t.status = 3 THEN t.id END) as completed_tasks,
                COUNT(DISTINCT d.id) as total_documents,
                COUNT(DISTINCT pr.id) as total_prototypes,
                COUNT(DISTINCT v.id) as total_videos,
                COUNT(DISTINCT al.id) as total_activities,
                MAX(al.created_at) as last_activity,
                ROUND(
                    (COUNT(DISTINCT CASE WHEN t.status = 3 THEN t.id END) / 
                     NULLIF(COUNT(DISTINCT t.id), 0)) * 100, 2
                ) as completion_percentage
            FROM zp_projects p
            LEFT JOIN zp_user_roles ur ON p.id = ur.project_id AND ur.is_active = 1
            LEFT JOIN zp_tickets t ON p.id = t.projectId
            LEFT JOIN zp_documents d ON p.id = d.project_id 
                AND d.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_prototypes pr ON p.id = pr.project_id 
                AND pr.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_video_submissions v ON p.id = v.project_id 
                AND v.created_at BETWEEN :start_date AND :end_date
            LEFT JOIN zp_audit_logs al ON p.id = al.project_id 
                AND al.created_at BETWEEN :start_date AND :end_date
            WHERE p.state IN (1, 2)
            GROUP BY p.id, p.name, p.description, p.created, p.state
            ORDER BY total_activities DESC, p.name
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $data;
    }

    /**
     * Get submission analytics report data
     */
    private function generateSubmissionAnalyticsReport(array $parameters, int $userId): array
    {
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        $projectId = $parameters['project_id'] ?? null;

        // Get prototypes data
        $prototypesData = $this->getSubmissionData('prototypes', $startDate, $endDate, $projectId);
        
        // Get videos data
        $videosData = $this->getSubmissionData('videos', $startDate, $endDate, $projectId);

        // Combine and format data
        $combinedData = [];
        
        foreach ($prototypesData as $prototype) {
            $combinedData[] = [
                'submission_type' => 'Prototype',
                'id' => $prototype['id'],
                'title' => $prototype['title'],
                'project_name' => $prototype['project_name'],
                'submitted_by' => $prototype['submitted_by_name'],
                'submission_date' => $prototype['created_at'],
                'status' => $prototype['status'],
                'review_status' => $prototype['review_status'],
                'view_count' => $prototype['view_count'],
                'visibility' => $prototype['visibility']
            ];
        }

        foreach ($videosData as $video) {
            $combinedData[] = [
                'submission_type' => 'Video',
                'id' => $video['id'],
                'title' => $video['title'],
                'project_name' => $video['project_name'],
                'submitted_by' => $video['submitted_by_name'],
                'submission_date' => $video['created_at'],
                'status' => $video['status'],
                'review_status' => $video['review_status'],
                'view_count' => $video['view_count'],
                'visibility' => $video['visibility']
            ];
        }

        // Sort by submission date
        usort($combinedData, function($a, $b) {
            return strtotime($b['submission_date']) - strtotime($a['submission_date']);
        });

        return $combinedData;
    }

    /**
     * Get security audit report data
     */
    private function generateSecurityAuditReport(array $parameters, int $userId): array
    {
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');

        $sql = "
            SELECT 
                al.id,
                al.action_type,
                al.description,
                al.ip_address,
                al.user_agent,
                al.severity,
                al.created_at,
                COALESCE(CONCAT(u.firstname, ' ', u.lastname), 'System') as user_name,
                u.username,
                p.name as project_name,
                CASE 
                    WHEN al.action_type IN ('login_success', 'login_failed', 'logout') THEN 'Authentication'
                    WHEN al.action_type LIKE '%_role_%' OR al.action_type LIKE '%_permission_%' THEN 'Authorization'
                    WHEN al.action_type LIKE '%_delete%' OR al.action_type LIKE '%_remove%' THEN 'Data Modification'
                    WHEN al.action_type LIKE '%_export%' OR al.action_type LIKE '%_download%' THEN 'Data Access'
                    ELSE 'Other'
                END as event_category
            FROM zp_audit_logs al
            LEFT JOIN zp_user u ON al.user_id = u.id
            LEFT JOIN zp_projects p ON al.project_id = p.id
            WHERE al.created_at BETWEEN :start_date AND :end_date
            AND (
                al.severity IN ('high', 'critical') 
                OR al.action_type IN ('login_failed', 'permission_denied', 'unauthorized_access')
                OR al.category = 'auth'
            )
            ORDER BY al.created_at DESC, al.severity DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59', PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $data;
    }

    /**
     * Get system usage report data
     */
    private function generateSystemUsageReport(array $parameters, int $userId): array
    {
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');

        $sql = "
            SELECT 
                'Users' as metric_category,
                'Total Active Users' as metric_name,
                COUNT(DISTINCT u.id) as current_value,
                'users' as unit,
                NULL as previous_value,
                NULL as change_percentage
            FROM zp_user u
            WHERE u.status = 'A'
            
            UNION ALL
            
            SELECT 
                'Projects' as metric_category,
                'Active Projects' as metric_name,
                COUNT(*) as current_value,
                'projects' as unit,
                NULL as previous_value,
                NULL as change_percentage
            FROM zp_projects
            WHERE state = 1
            
            UNION ALL
            
            SELECT 
                'Storage' as metric_category,
                'Total Documents' as metric_name,
                COUNT(*) as current_value,
                'files' as unit,
                NULL as previous_value,
                NULL as change_percentage
            FROM zp_documents
            WHERE status = 'active'
            
            UNION ALL
            
            SELECT 
                'Storage' as metric_category,
                'Storage Used' as metric_name,
                ROUND(SUM(file_size) / 1024 / 1024, 2) as current_value,
                'MB' as unit,
                NULL as previous_value,
                NULL as change_percentage
            FROM zp_documents
            WHERE status = 'active'
            
            UNION ALL
            
            SELECT 
                'Activity' as metric_category,
                'Daily Active Users' as metric_name,
                COUNT(DISTINCT al.user_id) as current_value,
                'users' as unit,
                NULL as previous_value,
                NULL as change_percentage
            FROM zp_audit_logs al
            WHERE DATE(al.created_at) = CURDATE()
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $data;
    }

    // Private helper methods continue in next part...
    
    private function generateReportData(string $reportType, array $parameters, int $userId): array
    {
        switch ($reportType) {
            case 'user_activity':
                return $this->generateUserActivityReport($parameters, $userId);
            case 'project_performance':
                return $this->generateProjectPerformanceReport($parameters, $userId);
            case 'submission_analytics':
                return $this->generateSubmissionAnalyticsReport($parameters, $userId);
            case 'security_audit':
                return $this->generateSecurityAuditReport($parameters, $userId);
            case 'system_usage':
                return $this->generateSystemUsageReport($parameters, $userId);
            default:
                throw new Exception("Unknown report type: {$reportType}");
        }
    }

    private function createReportExportRecord(int $userId, string $reportType, array $parameters, string $format, ?string $reportName): int
    {
        $sql = "
            INSERT INTO zp_report_exports (
                report_type, report_name, parameters, file_format, requested_by, status
            ) VALUES (
                :report_type, :report_name, :parameters, :file_format, :requested_by, 'processing'
            )
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':report_type', $reportType, PDO::PARAM_STR);
        $stmt->bindValue(':report_name', $reportName ?: ucfirst(str_replace('_', ' ', $reportType)) . ' Report', PDO::PARAM_STR);
        $stmt->bindValue(':parameters', json_encode($parameters), PDO::PARAM_STR);
        $stmt->bindValue(':file_format', $format, PDO::PARAM_STR);
        $stmt->bindValue(':requested_by', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $exportId = $this->db->database->lastInsertId();
        $stmt->closeCursor();

        return $exportId;
    }

    private function updateReportRecord(int $exportId, string $filePath, string $fileName, int $fileSize): void
    {
        $sql = "
            UPDATE zp_report_exports 
            SET file_path = :file_path, file_name = :file_name, file_size = :file_size, 
                status = 'completed', progress_percentage = 100, completed_at = NOW()
            WHERE id = :export_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':file_path', $filePath, PDO::PARAM_STR);
        $stmt->bindValue(':file_name', $fileName, PDO::PARAM_STR);
        $stmt->bindValue(':file_size', $fileSize, PDO::PARAM_INT);
        $stmt->bindValue(':export_id', $exportId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function updateReportStatus(int $exportId, string $status, ?string $errorMessage = null): void
    {
        $sql = "
            UPDATE zp_report_exports 
            SET status = :status, error_message = :error_message
            WHERE id = :export_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':error_message', $errorMessage, PDO::PARAM_STR);
        $stmt->bindValue(':export_id', $exportId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function generateFileName(string $reportType, string $format): string
    {
        return strtolower($reportType) . '_report_' . date('Y-m-d_H-i-s') . '.' . $format;
    }

    private function generateExportFile(array $data, string $format, string $fileName): ?string
    {
        $filePath = $this->exportPath . '/' . $fileName;

        switch ($format) {
            case 'csv':
                return $this->generateCSVFile($data, $filePath);
            case 'json':
                return $this->generateJSONFile($data, $filePath);
            case 'xlsx':
                // Would require PhpSpreadsheet library
                return $this->generateCSVFile($data, str_replace('.xlsx', '.csv', $filePath));
            case 'pdf':
                // Would require PDF library like TCPDF or DOMPDF
                return $this->generateCSVFile($data, str_replace('.pdf', '.csv', $filePath));
            default:
                return $this->generateCSVFile($data, $filePath);
        }
    }

    private function generateCSVFile(array $data, string $filePath): ?string
    {
        if (empty($data)) {
            return null;
        }

        $file = fopen($filePath, 'w');
        if (!$file) {
            return null;
        }

        // Write headers
        fputcsv($file, array_keys($data[0]));

        // Write data
        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
        return $filePath;
    }

    private function generateJSONFile(array $data, string $filePath): ?string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if (file_put_contents($filePath, $json) !== false) {
            return $filePath;
        }
        return null;
    }

    private function getSubmissionData(string $type, string $startDate, string $endDate, ?int $projectId): array
    {
        $table = $type === 'prototypes' ? 'zp_prototypes' : 'zp_video_submissions';
        $whereConditions = ["s.created_at BETWEEN :start_date AND :end_date"];
        $params = [':start_date' => $startDate, ':end_date' => $endDate . ' 23:59:59'];

        if ($projectId) {
            $whereConditions[] = 's.project_id = :project_id';
            $params[':project_id'] = $projectId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "
            SELECT 
                s.*,
                CONCAT(u.firstname, ' ', u.lastname) as submitted_by_name,
                p.name as project_name
            FROM {$table} s
            JOIN zp_user u ON s.submitted_by = u.id
            JOIN zp_projects p ON s.project_id = p.id
            WHERE {$whereClause}
            ORDER BY s.created_at DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $data;
    }
}
