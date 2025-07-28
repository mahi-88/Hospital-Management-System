<?php

namespace Leantime\Domain\Projects\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Core\Environment\Environment;
use Leantime\Core\Mail\Mail;
use Leantime\Domain\Auth\Services\RBACService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;
use PDO;
use DateTime;

/**
 * PixelForge Nexus Project Invitation Service
 * 
 * Handles project team invitations and onboarding
 */
class ProjectInvitationService
{
    private DbCore $db;
    private Environment $config;
    private Mail $mail;
    private RBACService $rbacService;
    private UserRepository $userRepository;

    public function __construct(
        DbCore $db, 
        Environment $config, 
        Mail $mail, 
        RBACService $rbacService,
        UserRepository $userRepository
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->mail = $mail;
        $this->rbacService = $rbacService;
        $this->userRepository = $userRepository;
    }

    /**
     * Send project invitation
     */
    public function sendInvitation(
        int $projectId, 
        string $email, 
        int $roleId, 
        int $invitedBy, 
        ?string $message = null,
        int $expirationDays = 7
    ): array {
        // Check if user already exists
        $existingUser = $this->userRepository->getUserByEmail($email);
        if ($existingUser) {
            // User exists, check if already in project
            $userRoles = $this->rbacService->getUserRoles($existingUser['id'], $projectId);
            if (!empty($userRoles)) {
                return ['success' => false, 'message' => 'User is already a member of this project'];
            }
        }

        // Check for existing pending invitation
        $existingInvitation = $this->getPendingInvitation($projectId, $email);
        if ($existingInvitation) {
            return ['success' => false, 'message' => 'Invitation already sent to this email'];
        }

        // Generate unique invitation token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new DateTime();
        $expiresAt->modify("+{$expirationDays} days");

        // Insert invitation
        $sql = "
            INSERT INTO zp_project_invitations 
            (project_id, invited_by, email, role_id, invitation_token, message, expires_at)
            VALUES (:project_id, :invited_by, :email, :role_id, :token, :message, :expires_at)
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':invited_by', $invitedBy, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);

        if (!$stmt->execute()) {
            $stmt->closeCursor();
            return ['success' => false, 'message' => 'Failed to create invitation'];
        }

        $invitationId = $this->db->database->lastInsertId();
        $stmt->closeCursor();

        // Get project and role details for email
        $projectDetails = $this->getProjectDetails($projectId);
        $roleDetails = $this->getRoleDetails($roleId);
        $inviterDetails = $this->userRepository->getUser($invitedBy);

        // Send invitation email
        $emailSent = $this->sendInvitationEmail(
            $email, 
            $projectDetails, 
            $roleDetails, 
            $inviterDetails, 
            $token, 
            $message
        );

        if (!$emailSent) {
            // Delete invitation if email failed
            $this->deleteInvitation($invitationId);
            return ['success' => false, 'message' => 'Failed to send invitation email'];
        }

        return [
            'success' => true, 
            'message' => 'Invitation sent successfully',
            'invitation_id' => $invitationId,
            'token' => $token
        ];
    }

    /**
     * Accept project invitation
     */
    public function acceptInvitation(string $token, ?int $userId = null): array
    {
        $invitation = $this->getInvitationByToken($token);
        
        if (!$invitation) {
            return ['success' => false, 'message' => 'Invalid invitation token'];
        }

        if ($invitation['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invitation has already been processed'];
        }

        if (new DateTime() > new DateTime($invitation['expires_at'])) {
            $this->updateInvitationStatus($invitation['id'], 'expired');
            return ['success' => false, 'message' => 'Invitation has expired'];
        }

        // If user is not logged in, they need to register/login first
        if (!$userId) {
            return [
                'success' => false, 
                'message' => 'Please login or register to accept invitation',
                'requires_auth' => true,
                'invitation_data' => [
                    'email' => $invitation['email'],
                    'project_name' => $invitation['project_name'],
                    'role_name' => $invitation['role_display_name']
                ]
            ];
        }

        // Verify email matches
        $user = $this->userRepository->getUser($userId);
        if ($user['username'] !== $invitation['email']) {
            return ['success' => false, 'message' => 'Email address does not match invitation'];
        }

        // Assign role to user
        $roleAssigned = $this->rbacService->assignRoleToUser(
            $userId, 
            $invitation['role_id'], 
            $invitation['project_id'],
            $invitation['invited_by']
        );

        if (!$roleAssigned) {
            return ['success' => false, 'message' => 'Failed to assign role to user'];
        }

        // Update invitation status
        $this->updateInvitationStatus($invitation['id'], 'accepted', $userId);

        // Log team activity
        $this->logTeamActivity(
            $invitation['project_id'],
            $userId,
            'user_joined',
            'project',
            $invitation['project_id'],
            "User joined project as {$invitation['role_display_name']}"
        );

        return [
            'success' => true, 
            'message' => 'Successfully joined project',
            'project_id' => $invitation['project_id'],
            'role_name' => $invitation['role_display_name']
        ];
    }

    /**
     * Decline project invitation
     */
    public function declineInvitation(string $token): array
    {
        $invitation = $this->getInvitationByToken($token);
        
        if (!$invitation) {
            return ['success' => false, 'message' => 'Invalid invitation token'];
        }

        if ($invitation['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Invitation has already been processed'];
        }

        $this->updateInvitationStatus($invitation['id'], 'declined');

        return ['success' => true, 'message' => 'Invitation declined'];
    }

    /**
     * Get project invitations
     */
    public function getProjectInvitations(int $projectId, ?string $status = null): array
    {
        $sql = "
            SELECT 
                pi.*,
                p.name as project_name,
                r.display_name as role_display_name,
                u.firstname as inviter_firstname,
                u.lastname as inviter_lastname
            FROM zp_project_invitations pi
            JOIN zp_projects p ON pi.project_id = p.id
            JOIN zp_roles r ON pi.role_id = r.id
            JOIN zp_user u ON pi.invited_by = u.id
            WHERE pi.project_id = :project_id
        ";

        if ($status) {
            $sql .= " AND pi.status = :status";
        }

        $sql .= " ORDER BY pi.created_at DESC";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }

        $stmt->execute();
        $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $invitations;
    }

    /**
     * Get user invitations
     */
    public function getUserInvitations(string $email, ?string $status = null): array
    {
        $sql = "
            SELECT 
                pi.*,
                p.name as project_name,
                p.description as project_description,
                r.display_name as role_display_name,
                u.firstname as inviter_firstname,
                u.lastname as inviter_lastname
            FROM zp_project_invitations pi
            JOIN zp_projects p ON pi.project_id = p.id
            JOIN zp_roles r ON pi.role_id = r.id
            JOIN zp_user u ON pi.invited_by = u.id
            WHERE pi.email = :email
        ";

        if ($status) {
            $sql .= " AND pi.status = :status";
        }

        $sql .= " ORDER BY pi.created_at DESC";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        
        if ($status) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }

        $stmt->execute();
        $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $invitations;
    }

    /**
     * Cancel invitation
     */
    public function cancelInvitation(int $invitationId): bool
    {
        return $this->updateInvitationStatus($invitationId, 'expired');
    }

    /**
     * Resend invitation
     */
    public function resendInvitation(int $invitationId): array
    {
        $invitation = $this->getInvitationById($invitationId);
        
        if (!$invitation || $invitation['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Cannot resend this invitation'];
        }

        // Extend expiration
        $expiresAt = new DateTime();
        $expiresAt->modify('+7 days');

        $sql = "UPDATE zp_project_invitations SET expires_at = :expires_at WHERE id = :id";
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':id', $invitationId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        // Resend email
        $projectDetails = $this->getProjectDetails($invitation['project_id']);
        $roleDetails = $this->getRoleDetails($invitation['role_id']);
        $inviterDetails = $this->userRepository->getUser($invitation['invited_by']);

        $emailSent = $this->sendInvitationEmail(
            $invitation['email'],
            $projectDetails,
            $roleDetails,
            $inviterDetails,
            $invitation['invitation_token'],
            $invitation['message']
        );

        return [
            'success' => $emailSent,
            'message' => $emailSent ? 'Invitation resent successfully' : 'Failed to resend invitation'
        ];
    }

    // Private helper methods...
    private function getPendingInvitation(int $projectId, string $email): ?array
    {
        $sql = "
            SELECT * FROM zp_project_invitations 
            WHERE project_id = :project_id AND email = :email AND status = 'pending'
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: null;
    }

    private function getInvitationByToken(string $token): ?array
    {
        $sql = "
            SELECT 
                pi.*,
                p.name as project_name,
                r.display_name as role_display_name
            FROM zp_project_invitations pi
            JOIN zp_projects p ON pi.project_id = p.id
            JOIN zp_roles r ON pi.role_id = r.id
            WHERE pi.invitation_token = :token
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: null;
    }

    private function getInvitationById(int $id): ?array
    {
        $sql = "SELECT * FROM zp_project_invitations WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: null;
    }

    private function updateInvitationStatus(int $invitationId, string $status, ?int $acceptedBy = null): bool
    {
        $sql = "
            UPDATE zp_project_invitations 
            SET status = :status, accepted_at = :accepted_at, accepted_by = :accepted_by 
            WHERE id = :id
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':accepted_at', $status === 'accepted' ? date('Y-m-d H:i:s') : null, PDO::PARAM_STR);
        $stmt->bindValue(':accepted_by', $acceptedBy, PDO::PARAM_INT);
        $stmt->bindValue(':id', $invitationId, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        $stmt->closeCursor();

        return $result;
    }

    private function deleteInvitation(int $invitationId): bool
    {
        $sql = "DELETE FROM zp_project_invitations WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $invitationId, PDO::PARAM_INT);
        $result = $stmt->execute();
        $stmt->closeCursor();

        return $result;
    }

    private function getProjectDetails(int $projectId): array
    {
        $sql = "SELECT id, name, description FROM zp_projects WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: [];
    }

    private function getRoleDetails(int $roleId): array
    {
        $sql = "SELECT id, name, display_name, description FROM zp_roles WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $result ?: [];
    }

    private function sendInvitationEmail(
        string $email, 
        array $project, 
        array $role, 
        array $inviter, 
        string $token, 
        ?string $message
    ): bool {
        $invitationUrl = $this->config->appUrl . "/auth/invitation/accept?token=" . $token;
        
        $subject = "Invitation to join {$project['name']} on PixelForge Nexus";
        
        $body = "
            <h2>You've been invited to join a project!</h2>
            <p>Hello,</p>
            <p>{$inviter['firstname']} {$inviter['lastname']} has invited you to join the project <strong>{$project['name']}</strong> as a <strong>{$role['display_name']}</strong>.</p>
            
            " . ($message ? "<p><strong>Personal message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
            
            <p><strong>Project:</strong> {$project['name']}</p>
            <p><strong>Role:</strong> {$role['display_name']}</p>
            <p><strong>Description:</strong> " . ($project['description'] ?: 'No description provided') . "</p>
            
            <p>
                <a href='{$invitationUrl}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                    Accept Invitation
                </a>
            </p>
            
            <p>Or copy and paste this link into your browser:<br>
            <a href='{$invitationUrl}'>{$invitationUrl}</a></p>
            
            <p>This invitation will expire in 7 days.</p>
            
            <hr>
            <p><small>PixelForge Nexus - Secure Game Development Management System</small></p>
        ";

        return $this->mail->send($email, $subject, $body);
    }

    private function logTeamActivity(
        int $projectId, 
        int $userId, 
        string $activityType, 
        string $entityType, 
        int $entityId, 
        string $description,
        ?array $metadata = null
    ): void {
        $sql = "
            INSERT INTO zp_team_activity 
            (project_id, user_id, activity_type, entity_type, entity_id, description, metadata)
            VALUES (:project_id, :user_id, :activity_type, :entity_type, :entity_id, :description, :metadata)
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':metadata', $metadata ? json_encode($metadata) : null, PDO::PARAM_STR);
        
        $stmt->execute();
        $stmt->closeCursor();
    }
}
