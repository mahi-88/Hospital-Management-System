<?php

namespace Leantime\Domain\Prototypes\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Auth\Services\RBACService;
use PDO;
use Exception;

/**
 * PixelForge Nexus Video Submission Service
 * 
 * Handles video submissions, demos, and progress updates with multi-platform support
 */
class VideoSubmissionService
{
    private DbCore $db;
    private RBACService $rbacService;

    public function __construct(DbCore $db, RBACService $rbacService)
    {
        $this->db = $db;
        $this->rbacService = $rbacService;
    }

    /**
     * Submit a new video
     */
    public function submitVideo(
        int $projectId,
        int $userId,
        string $title,
        string $videoUrl,
        ?string $description = null,
        string $submissionType = 'progress_update',
        array $tags = [],
        string $visibility = 'team'
    ): array {
        // Check permissions
        if (!$this->rbacService->userHasPermission($userId, 'submit_video', $projectId)) {
            return ['success' => false, 'message' => 'Insufficient permissions to submit videos'];
        }

        // Process video URL and extract metadata
        $processedVideo = $this->processVideoUrl($videoUrl);
        if (!$processedVideo['valid']) {
            return ['success' => false, 'message' => $processedVideo['message']];
        }

        try {
            $this->db->database->beginTransaction();

            $sql = "
                INSERT INTO zp_video_submissions (
                    project_id, title, description, video_url, video_platform, 
                    video_id, thumbnail_url, duration, submission_type, status, 
                    visibility, submitted_by, submitted_at, tags, metadata
                ) VALUES (
                    :project_id, :title, :description, :video_url, :video_platform,
                    :video_id, :thumbnail_url, :duration, :submission_type, 'submitted',
                    :visibility, :submitted_by, NOW(), :tags, :metadata
                )
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':video_url', $videoUrl, PDO::PARAM_STR);
            $stmt->bindValue(':video_platform', $processedVideo['platform'], PDO::PARAM_STR);
            $stmt->bindValue(':video_id', $processedVideo['video_id'], PDO::PARAM_STR);
            $stmt->bindValue(':thumbnail_url', $processedVideo['thumbnail_url'], PDO::PARAM_STR);
            $stmt->bindValue(':duration', $processedVideo['duration'], PDO::PARAM_INT);
            $stmt->bindValue(':submission_type', $submissionType, PDO::PARAM_STR);
            $stmt->bindValue(':visibility', $visibility, PDO::PARAM_STR);
            $stmt->bindValue(':submitted_by', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':tags', json_encode($tags), PDO::PARAM_STR);
            $stmt->bindValue(':metadata', json_encode($processedVideo['metadata']), PDO::PARAM_STR);

            $stmt->execute();
            $videoId = $this->db->database->lastInsertId();
            $stmt->closeCursor();

            // Log submission activity
            $this->logSubmissionActivity($videoId, $userId, 'submit', 'video');

            // Log team activity
            $this->logTeamActivity(
                $projectId,
                $userId,
                'file_uploaded', // Using existing enum
                'video',
                $videoId,
                "Submitted video: {$title}"
            );

            $this->db->database->commit();

            return [
                'success' => true,
                'message' => 'Video submitted successfully',
                'video_id' => $videoId
            ];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to submit video: ' . $e->getMessage()];
        }
    }

    /**
     * Get video with access control
     */
    public function getVideo(int $videoId, int $userId): ?array
    {
        $sql = "
            SELECT 
                v.*,
                u.firstname as submitted_by_firstname,
                u.lastname as submitted_by_lastname,
                u.profileId as submitted_by_avatar,
                reviewer.firstname as reviewed_by_firstname,
                reviewer.lastname as reviewed_by_lastname,
                proj.name as project_name
            FROM zp_video_submissions v
            JOIN zp_user u ON v.submitted_by = u.id
            JOIN zp_projects proj ON v.project_id = proj.id
            LEFT JOIN zp_user reviewer ON v.reviewed_by = reviewer.id
            WHERE v.id = :video_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':video_id', $videoId, PDO::PARAM_INT);
        $stmt->execute();
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$video) {
            return null;
        }

        // Check access permissions
        if (!$this->canUserAccessVideo($userId, $video)) {
            return null;
        }

        // Decode JSON fields
        $video['tags'] = $video['tags'] ? json_decode($video['tags'], true) : [];
        $video['metadata'] = $video['metadata'] ? json_decode($video['metadata'], true) : [];

        // Add user permissions
        $video['can_edit'] = $this->canUserEditVideo($userId, $video);
        $video['can_delete'] = $this->canUserDeleteVideo($userId, $video);
        $video['can_review'] = $this->canUserReviewVideo($userId, $video);

        // Generate embed URL
        $video['embed_url'] = $this->generateEmbedUrl($video);

        // Log view
        $this->logSubmissionActivity($videoId, $userId, 'view', 'video');
        $this->incrementViewCount($videoId, 'video');

        return $video;
    }

    /**
     * Get project videos with filtering
     */
    public function getProjectVideos(
        int $projectId,
        int $userId,
        ?string $status = null,
        ?string $submissionType = null,
        ?string $visibility = null,
        int $page = 1,
        int $limit = 20
    ): array {
        // Check project access
        if (!$this->rbacService->userHasPermission($userId, 'view_video', $projectId)) {
            return ['videos' => [], 'total' => 0, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        $whereConditions = ['v.project_id = :project_id'];
        $params = [':project_id' => $projectId];

        // Apply filters
        if ($status) {
            $whereConditions[] = 'v.status = :status';
            $params[':status'] = $status;
        }

        if ($submissionType) {
            $whereConditions[] = 'v.submission_type = :submission_type';
            $params[':submission_type'] = $submissionType;
        }

        if ($visibility) {
            $whereConditions[] = 'v.visibility = :visibility';
            $params[':visibility'] = $visibility;
        }

        // Check visibility permissions
        $userRoles = $this->rbacService->getUserRoles($userId, $projectId);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            $whereConditions[] = "(v.visibility IN ('client', 'public') OR v.review_status = 'approved')";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM zp_video_submissions v WHERE {$whereClause}";
        $stmt = $this->db->database->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt->closeCursor();

        // Get videos
        $sql = "
            SELECT 
                v.*,
                u.firstname as submitted_by_firstname,
                u.lastname as submitted_by_lastname,
                u.profileId as submitted_by_avatar,
                reviewer.firstname as reviewed_by_firstname,
                reviewer.lastname as reviewed_by_lastname
            FROM zp_video_submissions v
            JOIN zp_user u ON v.submitted_by = u.id
            LEFT JOIN zp_user reviewer ON v.reviewed_by = reviewer.id
            WHERE {$whereClause}
            ORDER BY v.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process videos
        foreach ($videos as &$video) {
            $video['tags'] = $video['tags'] ? json_decode($video['tags'], true) : [];
            $video['metadata'] = $video['metadata'] ? json_decode($video['metadata'], true) : [];
            $video['can_edit'] = $this->canUserEditVideo($userId, $video);
            $video['can_delete'] = $this->canUserDeleteVideo($userId, $video);
            $video['can_review'] = $this->canUserReviewVideo($userId, $video);
            $video['embed_url'] = $this->generateEmbedUrl($video);
        }

        return [
            'videos' => $videos,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }

    /**
     * Review video (approve/reject)
     */
    public function reviewVideo(
        int $videoId,
        int $reviewerId,
        string $reviewStatus,
        ?string $comment = null,
        ?int $rating = null
    ): array {
        $video = $this->getVideoById($videoId);
        if (!$video) {
            return ['success' => false, 'message' => 'Video not found'];
        }

        if (!$this->canUserReviewVideo($reviewerId, $video)) {
            return ['success' => false, 'message' => 'Insufficient permissions to review video'];
        }

        $validStatuses = ['approved', 'rejected', 'needs_revision'];
        if (!in_array($reviewStatus, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid review status'];
        }

        try {
            $this->db->database->beginTransaction();

            // Update video review status
            $sql = "
                UPDATE zp_video_submissions 
                SET review_status = :review_status, reviewed_by = :reviewer_id, 
                    reviewed_at = NOW(), review_comment = :comment,
                    status = CASE 
                        WHEN :review_status = 'approved' THEN 'approved'
                        WHEN :review_status = 'rejected' THEN 'rejected'
                        ELSE 'in_review'
                    END
                WHERE id = :video_id
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':review_status', $reviewStatus, PDO::PARAM_STR);
            $stmt->bindValue(':reviewer_id', $reviewerId, PDO::PARAM_INT);
            $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindValue(':video_id', $videoId, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Add detailed review record
            if ($comment || $rating) {
                $this->addSubmissionReview(
                    'video',
                    $videoId,
                    $reviewerId,
                    $reviewStatus === 'approved' ? 'approval' : 'feedback',
                    $comment ?? '',
                    $rating
                );
            }

            // Log review activity
            $this->logSubmissionActivity($videoId, $reviewerId, 'review', 'video');

            // Log team activity
            $this->logTeamActivity(
                $video['project_id'],
                $reviewerId,
                'task_updated', // Using existing enum
                'video',
                $videoId,
                "Reviewed video: {$video['title']} - {$reviewStatus}"
            );

            $this->db->database->commit();

            return [
                'success' => true,
                'message' => 'Video review submitted successfully',
                'review_status' => $reviewStatus
            ];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to submit review: ' . $e->getMessage()];
        }
    }

    // Private helper methods
    private function processVideoUrl(string $url): array
    {
        // YouTube URL patterns
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $videoId = $matches[1];
            return [
                'valid' => true,
                'platform' => 'youtube',
                'video_id' => $videoId,
                'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                'duration' => null, // Would need API call to get duration
                'metadata' => [
                    'platform' => 'youtube',
                    'video_id' => $videoId,
                    'embed_url' => "https://www.youtube.com/embed/{$videoId}"
                ]
            ];
        }

        // Vimeo URL patterns
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            $videoId = $matches[1];
            return [
                'valid' => true,
                'platform' => 'vimeo',
                'video_id' => $videoId,
                'thumbnail_url' => null, // Would need API call to get thumbnail
                'duration' => null,
                'metadata' => [
                    'platform' => 'vimeo',
                    'video_id' => $videoId,
                    'embed_url' => "https://player.vimeo.com/video/{$videoId}"
                ]
            ];
        }

        // Loom URL patterns
        if (preg_match('/loom\.com\/share\/([a-zA-Z0-9]+)/', $url, $matches)) {
            $videoId = $matches[1];
            return [
                'valid' => true,
                'platform' => 'loom',
                'video_id' => $videoId,
                'thumbnail_url' => null,
                'duration' => null,
                'metadata' => [
                    'platform' => 'loom',
                    'video_id' => $videoId,
                    'embed_url' => "https://www.loom.com/embed/{$videoId}"
                ]
            ];
        }

        // Custom/direct video URLs
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => true,
                'platform' => 'custom',
                'video_id' => null,
                'thumbnail_url' => null,
                'duration' => null,
                'metadata' => [
                    'platform' => 'custom',
                    'direct_url' => $url
                ]
            ];
        }

        return ['valid' => false, 'message' => 'Invalid video URL format'];
    }

    private function generateEmbedUrl(array $video): ?string
    {
        $metadata = $video['metadata'] ? json_decode($video['metadata'], true) : [];
        
        if (isset($metadata['embed_url'])) {
            return $metadata['embed_url'];
        }

        switch ($video['video_platform']) {
            case 'youtube':
                return "https://www.youtube.com/embed/{$video['video_id']}";
            case 'vimeo':
                return "https://player.vimeo.com/video/{$video['video_id']}";
            case 'loom':
                return "https://www.loom.com/embed/{$video['video_id']}";
            case 'custom':
                return $video['video_url'];
            default:
                return null;
        }
    }

    private function getVideoById(int $videoId): ?array
    {
        $sql = "SELECT * FROM zp_video_submissions WHERE id = :id";
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $videoId, PDO::PARAM_INT);
        $stmt->execute();
        $video = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $video ?: null;
    }

    private function canUserAccessVideo(int $userId, array $video): bool
    {
        if (!$this->rbacService->userHasPermission($userId, 'view_video', $video['project_id'])) {
            return false;
        }

        $userRoles = $this->rbacService->getUserRoles($userId, $video['project_id']);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            return in_array($video['visibility'], ['client', 'public']) || $video['review_status'] === 'approved';
        }

        return true;
    }

    private function canUserEditVideo(int $userId, array $video): bool
    {
        return $this->rbacService->userHasPermission($userId, 'edit_video', $video['project_id']) ||
               $video['submitted_by'] == $userId;
    }

    private function canUserDeleteVideo(int $userId, array $video): bool
    {
        return $this->rbacService->userHasPermission($userId, 'delete_video', $video['project_id']) ||
               $video['submitted_by'] == $userId;
    }

    private function canUserReviewVideo(int $userId, array $video): bool
    {
        return $this->rbacService->userHasPermission($userId, 'review_video', $video['project_id']);
    }

    // Shared helper methods (same as PrototypeService)
    private function logSubmissionActivity(int $submissionId, int $userId, string $action, string $type): void
    {
        $sql = "
            INSERT INTO zp_submission_access_log 
            (submission_type, submission_id, user_id, action, ip_address, user_agent, session_id)
            VALUES (:type, :submission_id, :user_id, :action, :ip_address, :user_agent, :session_id)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':session_id', session_id(), PDO::PARAM_STR);
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

    private function incrementViewCount(int $submissionId, string $type): void
    {
        $table = $type === 'prototype' ? 'zp_prototypes' : 'zp_video_submissions';
        $sql = "UPDATE {$table} SET view_count = view_count + 1 WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $submissionId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
    }

    private function addSubmissionReview(string $type, int $submissionId, int $reviewerId, string $reviewType, string $comment, ?int $rating = null): void
    {
        $sql = "
            INSERT INTO zp_submission_reviews 
            (submission_type, submission_id, reviewer_id, review_type, comment, rating)
            VALUES (:type, :submission_id, :reviewer_id, :review_type, :comment, :rating)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
        $stmt->bindValue(':reviewer_id', $reviewerId, PDO::PARAM_INT);
        $stmt->bindValue(':review_type', $reviewType, PDO::PARAM_STR);
        $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
        $stmt->bindValue(':rating', $rating, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();
    }
}
