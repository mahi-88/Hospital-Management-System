<?php

namespace Leantime\Domain\Prototypes\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Auth\Services\RBACService;
use PDO;
use Exception;

/**
 * PixelForge Nexus Prototype Service
 * 
 * Handles Figma prototypes, design submissions, and review workflows
 */
class PrototypeService
{
    private DbCore $db;
    private RBACService $rbacService;

    public function __construct(DbCore $db, RBACService $rbacService)
    {
        $this->db = $db;
        $this->rbacService = $rbacService;
    }

    /**
     * Submit a new prototype
     */
    public function submitPrototype(
        int $projectId,
        int $userId,
        string $title,
        string $figmaUrl,
        ?string $description = null,
        string $prototypeType = 'figma',
        ?string $version = null,
        array $tags = [],
        string $visibility = 'team'
    ): array {
        // Check permissions
        if (!$this->rbacService->userHasPermission($userId, 'submit_prototype', $projectId)) {
            return ['success' => false, 'message' => 'Insufficient permissions to submit prototypes'];
        }

        // Validate and process Figma URL
        $processedUrl = $this->processFigmaUrl($figmaUrl);
        if (!$processedUrl['valid']) {
            return ['success' => false, 'message' => $processedUrl['message']];
        }

        try {
            $this->db->database->beginTransaction();

            $sql = "
                INSERT INTO zp_prototypes (
                    project_id, title, description, figma_url, figma_embed_url, 
                    prototype_type, version, status, visibility, submitted_by, 
                    submitted_at, tags, metadata
                ) VALUES (
                    :project_id, :title, :description, :figma_url, :figma_embed_url,
                    :prototype_type, :version, 'submitted', :visibility, :submitted_by,
                    NOW(), :tags, :metadata
                )
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':figma_url', $figmaUrl, PDO::PARAM_STR);
            $stmt->bindValue(':figma_embed_url', $processedUrl['embed_url'], PDO::PARAM_STR);
            $stmt->bindValue(':prototype_type', $prototypeType, PDO::PARAM_STR);
            $stmt->bindValue(':version', $version, PDO::PARAM_STR);
            $stmt->bindValue(':visibility', $visibility, PDO::PARAM_STR);
            $stmt->bindValue(':submitted_by', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':tags', json_encode($tags), PDO::PARAM_STR);
            $stmt->bindValue(':metadata', json_encode($processedUrl['metadata']), PDO::PARAM_STR);

            $stmt->execute();
            $prototypeId = $this->db->database->lastInsertId();
            $stmt->closeCursor();

            // Log submission activity
            $this->logSubmissionActivity($prototypeId, $userId, 'submit', 'prototype');

            // Log team activity
            $this->logTeamActivity(
                $projectId,
                $userId,
                'file_uploaded', // Using existing enum
                'prototype',
                $prototypeId,
                "Submitted prototype: {$title}"
            );

            $this->db->database->commit();

            return [
                'success' => true,
                'message' => 'Prototype submitted successfully',
                'prototype_id' => $prototypeId
            ];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to submit prototype: ' . $e->getMessage()];
        }
    }

    /**
     * Get prototype with access control
     */
    public function getPrototype(int $prototypeId, int $userId): ?array
    {
        $sql = "
            SELECT 
                p.*,
                u.firstname as submitted_by_firstname,
                u.lastname as submitted_by_lastname,
                u.profileId as submitted_by_avatar,
                reviewer.firstname as reviewed_by_firstname,
                reviewer.lastname as reviewed_by_lastname,
                proj.name as project_name
            FROM zp_prototypes p
            JOIN zp_user u ON p.submitted_by = u.id
            JOIN zp_projects proj ON p.project_id = proj.id
            LEFT JOIN zp_user reviewer ON p.reviewed_by = reviewer.id
            WHERE p.id = :prototype_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':prototype_id', $prototypeId, PDO::PARAM_INT);
        $stmt->execute();
        $prototype = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$prototype) {
            return null;
        }

        // Check access permissions
        if (!$this->canUserAccessPrototype($userId, $prototype)) {
            return null;
        }

        // Decode JSON fields
        $prototype['tags'] = $prototype['tags'] ? json_decode($prototype['tags'], true) : [];
        $prototype['metadata'] = $prototype['metadata'] ? json_decode($prototype['metadata'], true) : [];

        // Add user permissions
        $prototype['can_edit'] = $this->canUserEditPrototype($userId, $prototype);
        $prototype['can_delete'] = $this->canUserDeletePrototype($userId, $prototype);
        $prototype['can_review'] = $this->canUserReviewPrototype($userId, $prototype);

        // Log view
        $this->logSubmissionActivity($prototypeId, $userId, 'view', 'prototype');
        $this->incrementViewCount($prototypeId, 'prototype');

        return $prototype;
    }

    /**
     * Get project prototypes with filtering
     */
    public function getProjectPrototypes(
        int $projectId,
        int $userId,
        ?string $status = null,
        ?string $visibility = null,
        ?string $prototypeType = null,
        int $page = 1,
        int $limit = 20
    ): array {
        // Check project access
        if (!$this->rbacService->userHasPermission($userId, 'view_prototype', $projectId)) {
            return ['prototypes' => [], 'total' => 0, 'pages' => 0];
        }

        $offset = ($page - 1) * $limit;
        $whereConditions = ['p.project_id = :project_id'];
        $params = [':project_id' => $projectId];

        // Apply filters
        if ($status) {
            $whereConditions[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        if ($visibility) {
            $whereConditions[] = 'p.visibility = :visibility';
            $params[':visibility'] = $visibility;
        }

        if ($prototypeType) {
            $whereConditions[] = 'p.prototype_type = :prototype_type';
            $params[':prototype_type'] = $prototypeType;
        }

        // Check visibility permissions
        $userRoles = $this->rbacService->getUserRoles($userId, $projectId);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            $whereConditions[] = "(p.visibility IN ('client', 'public') OR p.review_status = 'approved')";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM zp_prototypes p WHERE {$whereClause}";
        $stmt = $this->db->database->prepare($countSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt->closeCursor();

        // Get prototypes
        $sql = "
            SELECT 
                p.*,
                u.firstname as submitted_by_firstname,
                u.lastname as submitted_by_lastname,
                u.profileId as submitted_by_avatar,
                reviewer.firstname as reviewed_by_firstname,
                reviewer.lastname as reviewed_by_lastname
            FROM zp_prototypes p
            JOIN zp_user u ON p.submitted_by = u.id
            LEFT JOIN zp_user reviewer ON p.reviewed_by = reviewer.id
            WHERE {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->database->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $prototypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Process prototypes
        foreach ($prototypes as &$prototype) {
            $prototype['tags'] = $prototype['tags'] ? json_decode($prototype['tags'], true) : [];
            $prototype['metadata'] = $prototype['metadata'] ? json_decode($prototype['metadata'], true) : [];
            $prototype['can_edit'] = $this->canUserEditPrototype($userId, $prototype);
            $prototype['can_delete'] = $this->canUserDeletePrototype($userId, $prototype);
            $prototype['can_review'] = $this->canUserReviewPrototype($userId, $prototype);
        }

        return [
            'prototypes' => $prototypes,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }

    /**
     * Review prototype (approve/reject)
     */
    public function reviewPrototype(
        int $prototypeId,
        int $reviewerId,
        string $reviewStatus,
        ?string $comment = null,
        ?int $rating = null
    ): array {
        $prototype = $this->getPrototypeById($prototypeId);
        if (!$prototype) {
            return ['success' => false, 'message' => 'Prototype not found'];
        }

        if (!$this->canUserReviewPrototype($reviewerId, $prototype)) {
            return ['success' => false, 'message' => 'Insufficient permissions to review prototype'];
        }

        $validStatuses = ['approved', 'rejected', 'needs_revision'];
        if (!in_array($reviewStatus, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid review status'];
        }

        try {
            $this->db->database->beginTransaction();

            // Update prototype review status
            $sql = "
                UPDATE zp_prototypes 
                SET review_status = :review_status, reviewed_by = :reviewer_id, 
                    reviewed_at = NOW(), review_comment = :comment,
                    status = CASE 
                        WHEN :review_status = 'approved' THEN 'approved'
                        WHEN :review_status = 'rejected' THEN 'rejected'
                        ELSE 'in_review'
                    END
                WHERE id = :prototype_id
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':review_status', $reviewStatus, PDO::PARAM_STR);
            $stmt->bindValue(':reviewer_id', $reviewerId, PDO::PARAM_INT);
            $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindValue(':prototype_id', $prototypeId, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Add detailed review record
            if ($comment || $rating) {
                $this->addSubmissionReview(
                    'prototype',
                    $prototypeId,
                    $reviewerId,
                    $reviewStatus === 'approved' ? 'approval' : 'feedback',
                    $comment ?? '',
                    $rating
                );
            }

            // Log review activity
            $this->logSubmissionActivity($prototypeId, $reviewerId, 'review', 'prototype');

            // Log team activity
            $this->logTeamActivity(
                $prototype['project_id'],
                $reviewerId,
                'task_updated', // Using existing enum
                'prototype',
                $prototypeId,
                "Reviewed prototype: {$prototype['title']} - {$reviewStatus}"
            );

            $this->db->database->commit();

            return [
                'success' => true,
                'message' => 'Prototype review submitted successfully',
                'review_status' => $reviewStatus
            ];

        } catch (Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to submit review: ' . $e->getMessage()];
        }
    }

    /**
     * Delete prototype
     */
    public function deletePrototype(int $prototypeId, int $userId): array
    {
        $prototype = $this->getPrototypeById($prototypeId);
        if (!$prototype) {
            return ['success' => false, 'message' => 'Prototype not found'];
        }

        if (!$this->canUserDeletePrototype($userId, $prototype)) {
            return ['success' => false, 'message' => 'Insufficient permissions to delete prototype'];
        }

        try {
            $sql = "DELETE FROM zp_prototypes WHERE id = :prototype_id";
            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':prototype_id', $prototypeId, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            // Log team activity
            $this->logTeamActivity(
                $prototype['project_id'],
                $userId,
                'task_updated', // Using existing enum
                'prototype',
                $prototypeId,
                "Deleted prototype: {$prototype['title']}"
            );

            return ['success' => true, 'message' => 'Prototype deleted successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete prototype: ' . $e->getMessage()];
        }
    }

    /**
     * Get prototype reviews
     */
    public function getPrototypeReviews(int $prototypeId): array
    {
        $sql = "
            SELECT
                sr.*,
                u.firstname,
                u.lastname,
                u.profileId as avatar
            FROM zp_submission_reviews sr
            JOIN zp_user u ON sr.reviewer_id = u.id
            WHERE sr.submission_type = 'prototype' AND sr.submission_id = :prototype_id
            ORDER BY sr.created_at DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':prototype_id', $prototypeId, PDO::PARAM_INT);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $reviews;
    }

    /**
     * Add reaction to prototype
     */
    public function addReaction(int $prototypeId, int $userId, string $reactionType = 'like'): array
    {
        $validReactions = ['like', 'love', 'wow', 'laugh', 'sad', 'angry'];
        if (!in_array($reactionType, $validReactions)) {
            return ['success' => false, 'message' => 'Invalid reaction type'];
        }

        try {
            $sql = "
                INSERT INTO zp_submission_reactions (submission_type, submission_id, user_id, reaction_type)
                VALUES ('prototype', :prototype_id, :user_id, :reaction_type)
                ON DUPLICATE KEY UPDATE reaction_type = :reaction_type
            ";

            $stmt = $this->db->database->prepare($sql);
            $stmt->bindValue(':prototype_id', $prototypeId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':reaction_type', $reactionType, PDO::PARAM_STR);
            $stmt->execute();
            $stmt->closeCursor();

            return ['success' => true, 'message' => 'Reaction added successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add reaction: ' . $e->getMessage()];
        }
    }

    // Private helper methods
    private function processFigmaUrl(string $url): array
    {
        // Validate Figma URL format
        if (!preg_match('/^https:\/\/www\.figma\.com\/(file|proto)\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return ['valid' => false, 'message' => 'Invalid Figma URL format'];
        }

        $fileType = $matches[1];
        $fileId = $matches[2];

        // Generate embed URL for iframe
        $embedUrl = "https://www.figma.com/embed?embed_host=share&url=" . urlencode($url);

        return [
            'valid' => true,
            'embed_url' => $embedUrl,
            'metadata' => [
                'file_type' => $fileType,
                'file_id' => $fileId,
                'platform' => 'figma'
            ]
        ];
    }

    private function getPrototypeById(int $prototypeId): ?array
    {
        $sql = "SELECT * FROM zp_prototypes WHERE id = :id";
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $prototypeId, PDO::PARAM_INT);
        $stmt->execute();
        $prototype = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $prototype ?: null;
    }

    private function canUserAccessPrototype(int $userId, array $prototype): bool
    {
        // Check basic project access
        if (!$this->rbacService->userHasPermission($userId, 'view_prototype', $prototype['project_id'])) {
            return false;
        }

        // Check visibility restrictions
        $userRoles = $this->rbacService->getUserRoles($userId, $prototype['project_id']);
        $isClientOrGuest = !empty(array_filter($userRoles, fn($role) => in_array($role['name'], ['client', 'guest'])));
        
        if ($isClientOrGuest) {
            return in_array($prototype['visibility'], ['client', 'public']) || $prototype['review_status'] === 'approved';
        }

        return true;
    }

    private function canUserEditPrototype(int $userId, array $prototype): bool
    {
        return $this->rbacService->userHasPermission($userId, 'edit_prototype', $prototype['project_id']) ||
               $prototype['submitted_by'] == $userId;
    }

    private function canUserDeletePrototype(int $userId, array $prototype): bool
    {
        return $this->rbacService->userHasPermission($userId, 'delete_prototype', $prototype['project_id']) ||
               $prototype['submitted_by'] == $userId;
    }

    private function canUserReviewPrototype(int $userId, array $prototype): bool
    {
        return $this->rbacService->userHasPermission($userId, 'review_prototype', $prototype['project_id']);
    }

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
