<?php

namespace Leantime\Domain\Documents\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Core\Environment\Environment;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Auth\Services\RBACService;
use PDO;
use Exception;

/**
 * PixelForge Nexus Document Service
 * 
 * Comprehensive document and asset management with security, versioning, and access control
 */
class DocumentService
{
    private DbCore $db;
    private Environment $config;
    private RBACService $rbacService;
    private string $storagePath;
    private string $thumbnailPath;
    private array $allowedMimeTypes;
    private int $maxFileSize;

    public function __construct(DbCore $db, Environment $config, RBACService $rbacService)
    {
        $this->db = $db;
        $this->config = $config;
        $this->rbacService = $rbacService;
        
        // Configure storage paths
        $this->storagePath = rtrim($config->userFilePath ?? 'storage', '/') . '/documents';
        $this->thumbnailPath = rtrim($config->userFilePath ?? 'storage', '/') . '/thumbnails';
        
        // Security configuration
        $this->allowedMimeTypes = [
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
            
            // Video
            'video/mp4',
            'video/webm',
            'video/ogg',
            'video/avi',
            'video/mov',
            'video/wmv',
            
            // Audio
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
            
            // Archives
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
            
            // Design files
            'application/x-photoshop',
            'application/x-sketch',
            'application/x-figma',
            
            // Code files
            'text/html',
            'text/css',
            'text/javascript',
            'application/json',
            'application/xml'
        ];
        
        $this->maxFileSize = 100 * 1024 * 1024; // 100MB default
    }

    /**
     * Upload document with security checks and metadata extraction
     */
    public function uploadDocument(
        array $fileData,
        int $projectId,
        int $uploaderId,
        ?int $folderId = null,
        ?string $description = null,
        ?array $tags = null,
        ?string $category = null
    ): array {
        // Validate permissions
        if (!$this->rbacService->userHasPermission($uploaderId, 'upload_document', $projectId)) {
            return ['success' => false, 'message' => 'Insufficient permissions to upload documents'];
        }

        // Validate file
        $validation = $this->validateFile($fileData);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        // Check for duplicates
        $fileHash = hash_file('sha256', $fileData['tmp_name']);
        $duplicate = $this->findDuplicateFile($fileHash, $projectId);
        if ($duplicate) {
            return [
                'success' => false, 
                'message' => 'File already exists in project',
                'existing_file' => $duplicate
            ];
        }

        try {
            $this->db->database->beginTransaction();

            // Generate unique filename
            $originalName = $fileData['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            
            // Create directory structure
            $projectPath = $this->createProjectDirectory($projectId);
            $filePath = $projectPath . '/' . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Extract metadata
            $metadata = $this->extractFileMetadata($filePath, $fileData['type']);
            
            // Determine document type
            $documentType = $this->determineDocumentType($fileData['type'], $extension);
            
            // Insert document record
            $documentId = $this->insertDocumentRecord([
                'project_id' => $projectId,
                'folder_id' => $folderId,
                'uploader_id' => $uploaderId,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'file_hash' => $fileHash,
                'mime_type' => $fileData['type'],
                'file_size' => $fileData['size'],
                'file_extension' => $extension,
                'document_type' => $documentType,
                'category' => $category,
                'description' => $description,
                'tags' => $tags ? json_encode($tags) : null,
                'metadata' => json_encode($metadata)
            ]);

            // Generate thumbnail for images and videos
            if (in_array($documentType, ['image', 'video'])) {
                $this->generateThumbnail($filePath, $documentId, $documentType);
            }

            // Log upload activity
            $this->logDocumentAccess($documentId, $uploaderId, 'upload', [
                'file_name' => $originalName,
                'file_size' => $fileData['size'],
                'mime_type' => $fileData['type']
            ]);

            // Log team activity
            $this->logTeamActivity(
                $projectId,
                $uploaderId,
                'file_uploaded',
                'file',
                $documentId,
                "Uploaded file: {$originalName}"
            );

            $this->db->database->commit();

            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'document_id' => $documentId,
                'file_name' => $fileName,
                'original_name' => $originalName
            ];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            
            // Clean up file if it was moved
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get document with access control
     */
    public function getDocument(int $documentId, int $userId): ?array
    {
        $sql = "
            SELECT 
                d.*,
                u.firstname as uploader_firstname,
                u.lastname as uploader_lastname,
                p.name as project_name,
                f.name as folder_name,
                approver.firstname as approver_firstname,
                approver.lastname as approver_lastname
            FROM zp_documents d
            JOIN zp_user u ON d.uploader_id = u.id
            JOIN zp_projects p ON d.project_id = p.id
            LEFT JOIN zp_document_folders f ON d.folder_id = f.id
            LEFT JOIN zp_user approver ON d.approved_by = approver.id
            WHERE d.id = :document_id AND d.status != 'deleted'
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':document_id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$document) {
            return null;
        }

        // Check access permissions
        if (!$this->canUserAccessDocument($userId, $document)) {
            return null;
        }

        // Decode JSON fields
        $document['tags'] = $document['tags'] ? json_decode($document['tags'], true) : [];
        $document['metadata'] = $document['metadata'] ? json_decode($document['metadata'], true) : [];

        return $document;
    }

    /**
     * Download document with access logging
     */
    public function downloadDocument(int $documentId, int $userId): array
    {
        $document = $this->getDocument($documentId, $userId);
        
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found or access denied'];
        }

        if (!file_exists($document['file_path'])) {
            return ['success' => false, 'message' => 'File not found on disk'];
        }

        // Log download
        $this->logDocumentAccess($documentId, $userId, 'download');
        
        // Increment download counter
        $this->incrementDownloadCount($documentId);

        return [
            'success' => true,
            'file_path' => $document['file_path'],
            'file_name' => $document['original_name'],
            'mime_type' => $document['mime_type'],
            'file_size' => $document['file_size']
        ];
    }

    /**
     * Delete document with permission check
     */
    public function deleteDocument(int $documentId, int $userId): array
    {
        $document = $this->getDocument($documentId, $userId);
        
        if (!$document) {
            return ['success' => false, 'message' => 'Document not found or access denied'];
        }

        // Check delete permissions
        $canDelete = $this->rbacService->userHasPermission($userId, 'delete_document', $document['project_id']) ||
                    $document['uploader_id'] == $userId;

        if (!$canDelete) {
            return ['success' => false, 'message' => 'Insufficient permissions to delete document'];
        }

        try {
            $this->db->database->beginTransaction();

            // Soft delete - mark as deleted
            $sql = "UPDATE zp_documents SET status = 'deleted', updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Log deletion
            $this->logDocumentAccess($documentId, $userId, 'delete');
            
            // Log team activity
            $this->logTeamActivity(
                $document['project_id'],
                $userId,
                'file_uploaded', // Using existing enum value
                'file',
                $documentId,
                "Deleted file: {$document['original_name']}"
            );

            $this->db->database->commit();

            return ['success' => true, 'message' => 'Document deleted successfully'];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to delete document: ' . $e->getMessage()];
        }
    }

    /**
     * Get project documents with filtering and pagination
     */
    public function getProjectDocuments(
        int $projectId, 
        int $userId, 
        ?int $folderId = null,
        ?string $documentType = null,
        ?string $status = null,
        int $page = 1,
        int $limit = 20
    ): array {
        // Check project access
        if (!$this->rbacService->userHasPermission($userId, 'view_document', $projectId)) {
            return ['documents' => [], 'total' => 0, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        $whereConditions = ['d.project_id = :project_id', "d.status != 'deleted'"];
        $params = [':project_id' => $projectId];

        // Apply filters
        if ($folderId !== null) {
            $whereConditions[] = 'd.folder_id = :folder_id';
            $params[':folder_id'] = $folderId;
        }

        if ($documentType) {
            $whereConditions[] = 'd.document_type = :document_type';
            $params[':document_type'] = $documentType;
        }

        if ($status) {
            $whereConditions[] = 'd.status = :status';
            $params[':status'] = $status;
        }

        // Check if user can see all documents or only approved ones
        $userRoles = $this->rbacService->getUserRoles($userId, $projectId);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            $whereConditions[] = "(d.is_public = 1 OR d.approval_status = 'approved')";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM zp_documents d WHERE {$whereClause}";
        $stmt = $this->db->database->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt->closeCursor();

        // Get documents
        $sql = "
            SELECT 
                d.*,
                u.firstname as uploader_firstname,
                u.lastname as uploader_lastname,
                f.name as folder_name,
                approver.firstname as approver_firstname,
                approver.lastname as approver_lastname
            FROM zp_documents d
            JOIN zp_user u ON d.uploader_id = u.id
            LEFT JOIN zp_document_folders f ON d.folder_id = f.id
            LEFT JOIN zp_user approver ON d.approved_by = approver.id
            WHERE {$whereClause}
            ORDER BY d.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process documents
        foreach ($documents as &$document) {
            $document['tags'] = $document['tags'] ? json_decode($document['tags'], true) : [];
            $document['metadata'] = $document['metadata'] ? json_decode($document['metadata'], true) : [];
            $document['can_edit'] = $this->canUserEditDocument($userId, $document);
            $document['can_delete'] = $this->canUserDeleteDocument($userId, $document);
            $document['thumbnail_url'] = $this->getThumbnailUrl($document['id'], $document['document_type']);
        }

        return [
            'documents' => $documents,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }

    // Private helper methods continue in next part...
    
    private function validateFile(array $fileData): array
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload error: ' . $fileData['error']];
        }

        if ($fileData['size'] > $this->maxFileSize) {
            return ['valid' => false, 'message' => 'File size exceeds maximum allowed size'];
        }

        if (!in_array($fileData['type'], $this->allowedMimeTypes)) {
            return ['valid' => false, 'message' => 'File type not allowed'];
        }

        // Additional security checks
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);

        if ($detectedMimeType !== $fileData['type']) {
            return ['valid' => false, 'message' => 'File type mismatch detected'];
        }

        return ['valid' => true, 'message' => 'File is valid'];
    }

    private function findDuplicateFile(string $fileHash, int $projectId): ?array
    {
        $sql = "
            SELECT id, original_name, created_at 
            FROM zp_documents 
            WHERE file_hash = :hash AND project_id = :project_id AND status != 'deleted'
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':hash', $fileHash, PDO::PARAM_STR);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: null;
    }

    private function createProjectDirectory(int $projectId): string
    {
        $year = date('Y');
        $month = date('m');
        $projectPath = "{$this->storagePath}/{$projectId}/{$year}/{$month}";
        
        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }
        
        return $projectPath;
    }

    private function extractFileMetadata(string $filePath, string $mimeType): array
    {
        $metadata = [];
        
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['aspect_ratio'] = round($imageInfo[0] / $imageInfo[1], 2);
            }
        }
        
        return $metadata;
    }

    private function determineDocumentType(string $mimeType, string $extension): string
    {
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'video/') === 0) return 'video';
        if (strpos($mimeType, 'audio/') === 0) return 'audio';
        if (in_array($mimeType, ['application/zip', 'application/x-rar-compressed'])) return 'archive';
        if (in_array($extension, ['html', 'css', 'js', 'php', 'py', 'java'])) return 'code';
        if (in_array($extension, ['psd', 'sketch', 'fig', 'ai'])) return 'design';
        
        return 'document';
    }

    private function insertDocumentRecord(array $data): int
    {
        $sql = "
            INSERT INTO zp_documents (
                project_id, folder_id, uploader_id, file_name, original_name, file_path,
                file_hash, mime_type, file_size, file_extension, document_type, category,
                description, tags, metadata, version
            ) VALUES (
                :project_id, :folder_id, :uploader_id, :file_name, :original_name, :file_path,
                :file_hash, :mime_type, :file_size, :file_extension, :document_type, :category,
                :description, :tags, :metadata, 1
            )
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        $documentId = $this->db->database->lastInsertId();
        $stmt->closeCursor();

        return $documentId;
    }

    private function canUserAccessDocument(int $userId, array $document): bool
    {
        // Check basic project access
        if (!$this->rbacService->userHasPermission($userId, 'view_document', $document['project_id'])) {
            return false;
        }

        // Check if document is public or approved for clients/guests
        $userRoles = $this->rbacService->getUserRoles($userId, $document['project_id']);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            return $document['is_public'] == 1 || $document['approval_status'] === 'approved';
        }

        return true;
    }

    private function canUserEditDocument(int $userId, array $document): bool
    {
        return $this->rbacService->userHasPermission($userId, 'edit_document', $document['project_id']) ||
               $document['uploader_id'] == $userId;
    }

    private function canUserDeleteDocument(int $userId, array $document): bool
    {
        return $this->rbacService->userHasPermission($userId, 'delete_document', $document['project_id']) ||
               $document['uploader_id'] == $userId;
    }

    private function logDocumentAccess(int $documentId, int $userId, string $action, ?array $details = null): void
    {
        $sql = "
            INSERT INTO zp_document_access_log (document_id, user_id, action, ip_address, user_agent, details)
            VALUES (:document_id, :user_id, :action, :ip_address, :user_agent, :details)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':document_id', $documentId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':details', $details ? json_encode($details) : null, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function logTeamActivity(int $projectId, int $userId, string $activityType, string $entityType, int $entityId, string $description): void
    {
        $sql = "
            INSERT INTO zp_team_activity (project_id, user_id, activity_type, entity_type, entity_id, description)
            VALUES (:project_id, :user_id, :activity_type, :entity_type, :entity_id, :description)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function incrementDownloadCount(int $documentId): void
    {
        $sql = "UPDATE zp_documents SET download_count = download_count + 1 WHERE id = :id";
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function generateThumbnail(string $filePath, int $documentId, string $documentType): void
    {
        // Thumbnail generation implementation would go here
        // This is a placeholder for the actual thumbnail generation logic
    }

    private function getThumbnailUrl(int $documentId, string $documentType): ?string
    {
        // Return thumbnail URL if available
        $thumbnailPath = "{$this->thumbnailPath}/{$documentId}/thumb.jpg";
        return file_exists($thumbnailPath) ? "/thumbnails/{$documentId}/thumb.jpg" : null;
    }
}
