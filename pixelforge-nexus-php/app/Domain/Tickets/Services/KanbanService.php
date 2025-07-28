<?php

namespace Leantime\Domain\Tickets\Services;

use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Auth\Services\Auth;
use PDO;

/**
 * PixelForge Nexus Kanban Service
 * 
 * Handles kanban board functionality for agile task management
 */
class KanbanService
{
    private DbCore $db;

    public function __construct(DbCore $db)
    {
        $this->db = $db;
    }

    /**
     * Get kanban board data for a project
     */
    public function getKanbanBoard(int $projectId, ?int $sprintId = null): array
    {
        $columns = [
            'backlog' => ['name' => 'Backlog', 'color' => '#6c757d', 'tickets' => []],
            'todo' => ['name' => 'To Do', 'color' => '#007bff', 'tickets' => []],
            'in_progress' => ['name' => 'In Progress', 'color' => '#ffc107', 'tickets' => []],
            'review' => ['name' => 'Review', 'color' => '#fd7e14', 'tickets' => []],
            'testing' => ['name' => 'Testing', 'color' => '#6f42c1', 'tickets' => []],
            'done' => ['name' => 'Done', 'color' => '#28a745', 'tickets' => []]
        ];

        $sql = "
            SELECT 
                t.*,
                u_assignee.firstname as assignee_firstname,
                u_assignee.lastname as assignee_lastname,
                u_assignee.profileId as assignee_avatar,
                u_reporter.firstname as reporter_firstname,
                u_reporter.lastname as reporter_lastname,
                e.name as epic_name,
                e.status as epic_status,
                s.name as sprint_name
            FROM zp_tickets t
            LEFT JOIN zp_user u_assignee ON t.assignee_id = u_assignee.id
            LEFT JOIN zp_user u_reporter ON t.reporter_id = u_reporter.id
            LEFT JOIN zp_epics e ON t.epic_id = e.id
            LEFT JOIN zp_sprints s ON t.sprint_id = s.id
            WHERE t.projectId = :project_id
        ";

        if ($sprintId) {
            $sql .= " AND (t.sprint_id = :sprint_id OR t.sprint_id IS NULL)";
        }

        $sql .= " ORDER BY t.kanban_order ASC, t.dateCreated DESC";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        
        if ($sprintId) {
            $stmt->bindValue(':sprint_id', $sprintId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Group tickets by kanban status
        foreach ($tickets as $ticket) {
            $status = $ticket['kanban_status'] ?? 'backlog';
            if (isset($columns[$status])) {
                $columns[$status]['tickets'][] = $this->formatTicketForKanban($ticket);
            }
        }

        return $columns;
    }

    /**
     * Update ticket kanban status
     */
    public function updateTicketStatus(int $ticketId, string $newStatus, ?int $newOrder = null): array
    {
        // Validate status
        $validStatuses = ['backlog', 'todo', 'in_progress', 'review', 'testing', 'done'];
        if (!in_array($newStatus, $validStatuses)) {
            return ['success' => false, 'message' => 'Invalid status'];
        }

        // Get current ticket
        $ticket = $this->getTicket($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        // Check permissions
        if (!Auth::userHasPermission('edit_task', $ticket['projectId'])) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        $oldStatus = $ticket['kanban_status'];

        // Update ticket status and order
        $sql = "
            UPDATE zp_tickets 
            SET kanban_status = :status, kanban_order = :order, editFrom = :user_id, editDate = NOW()
            WHERE id = :ticket_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindValue(':order', $newOrder ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', session('userdata.id'), PDO::PARAM_INT);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $stmt->closeCursor();
            return ['success' => false, 'message' => 'Failed to update ticket'];
        }
        $stmt->closeCursor();

        // Log activity
        $this->logTicketActivity(
            $ticket['projectId'],
            session('userdata.id'),
            'task_updated',
            $ticketId,
            "Moved ticket from {$oldStatus} to {$newStatus}",
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        // Auto-assign if moving to in_progress and no assignee
        if ($newStatus === 'in_progress' && !$ticket['assignee_id']) {
            $this->assignTicket($ticketId, session('userdata.id'));
        }

        return ['success' => true, 'message' => 'Ticket status updated'];
    }

    /**
     * Assign ticket to user
     */
    public function assignTicket(int $ticketId, int $userId): array
    {
        $ticket = $this->getTicket($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!Auth::userHasPermission('assign_task', $ticket['projectId'])) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        $sql = "
            UPDATE zp_tickets 
            SET assignee_id = :user_id, editFrom = :editor_id, editDate = NOW()
            WHERE id = :ticket_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':editor_id', session('userdata.id'), PDO::PARAM_INT);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $stmt->closeCursor();
            return ['success' => false, 'message' => 'Failed to assign ticket'];
        }
        $stmt->closeCursor();

        // Log activity
        $this->logTicketActivity(
            $ticket['projectId'],
            session('userdata.id'),
            'task_updated',
            $ticketId,
            "Assigned ticket to user",
            ['assignee_id' => $userId]
        );

        return ['success' => true, 'message' => 'Ticket assigned successfully'];
    }

    /**
     * Update ticket priority
     */
    public function updateTicketPriority(int $ticketId, string $priority): array
    {
        $validPriorities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($priority, $validPriorities)) {
            return ['success' => false, 'message' => 'Invalid priority'];
        }

        $ticket = $this->getTicket($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!Auth::userHasPermission('edit_task', $ticket['projectId'])) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        $sql = "
            UPDATE zp_tickets 
            SET priority = :priority, editFrom = :user_id, editDate = NOW()
            WHERE id = :ticket_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':priority', $priority, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', session('userdata.id'), PDO::PARAM_INT);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);

        $result = $stmt->execute();
        $stmt->closeCursor();

        if ($result) {
            $this->logTicketActivity(
                $ticket['projectId'],
                session('userdata.id'),
                'task_updated',
                $ticketId,
                "Updated ticket priority to {$priority}"
            );
        }

        return [
            'success' => $result,
            'message' => $result ? 'Priority updated' : 'Failed to update priority'
        ];
    }

    /**
     * Get ticket details
     */
    public function getTicketDetails(int $ticketId): ?array
    {
        $sql = "
            SELECT 
                t.*,
                u_assignee.firstname as assignee_firstname,
                u_assignee.lastname as assignee_lastname,
                u_assignee.profileId as assignee_avatar,
                u_reporter.firstname as reporter_firstname,
                u_reporter.lastname as reporter_lastname,
                u_creator.firstname as creator_firstname,
                u_creator.lastname as creator_lastname,
                e.name as epic_name,
                s.name as sprint_name,
                p.name as project_name
            FROM zp_tickets t
            LEFT JOIN zp_user u_assignee ON t.assignee_id = u_assignee.id
            LEFT JOIN zp_user u_reporter ON t.reporter_id = u_reporter.id
            LEFT JOIN zp_user u_creator ON t.userId = u_creator.id
            LEFT JOIN zp_epics e ON t.epic_id = e.id
            LEFT JOIN zp_sprints s ON t.sprint_id = s.id
            LEFT JOIN zp_projects p ON t.projectId = p.id
            WHERE t.id = :ticket_id
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $ticket ?: null;
    }

    /**
     * Get sprint tickets summary
     */
    public function getSprintSummary(int $sprintId): array
    {
        $sql = "
            SELECT 
                kanban_status,
                priority,
                COUNT(*) as count,
                SUM(story_points) as total_points,
                SUM(estimated_hours) as total_estimated,
                SUM(actual_hours) as total_actual
            FROM zp_tickets 
            WHERE sprint_id = :sprint_id
            GROUP BY kanban_status, priority
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':sprint_id', $sprintId, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $summary = [
            'total_tickets' => 0,
            'total_points' => 0,
            'total_estimated' => 0,
            'total_actual' => 0,
            'by_status' => [],
            'by_priority' => []
        ];

        foreach ($results as $row) {
            $summary['total_tickets'] += $row['count'];
            $summary['total_points'] += $row['total_points'] ?? 0;
            $summary['total_estimated'] += $row['total_estimated'] ?? 0;
            $summary['total_actual'] += $row['total_actual'] ?? 0;

            $summary['by_status'][$row['kanban_status']] = ($summary['by_status'][$row['kanban_status']] ?? 0) + $row['count'];
            $summary['by_priority'][$row['priority']] = ($summary['by_priority'][$row['priority']] ?? 0) + $row['count'];
        }

        return $summary;
    }

    /**
     * Reorder tickets within a column
     */
    public function reorderTickets(array $ticketIds, string $status): array
    {
        if (empty($ticketIds)) {
            return ['success' => true, 'message' => 'No tickets to reorder'];
        }

        // Verify all tickets belong to the same project and user has permission
        $firstTicket = $this->getTicket($ticketIds[0]);
        if (!$firstTicket || !Auth::userHasPermission('edit_task', $firstTicket['projectId'])) {
            return ['success' => false, 'message' => 'Insufficient permissions'];
        }

        $this->db->database->beginTransaction();

        try {
            foreach ($ticketIds as $order => $ticketId) {
                $sql = "
                    UPDATE zp_tickets 
                    SET kanban_order = :order, kanban_status = :status
                    WHERE id = :ticket_id
                ";

                $stmt = $this->db->database->prepare($sql);
                $stmt->bindValue(':order', $order, PDO::PARAM_INT);
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
                $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
                $stmt->execute();
                $stmt->closeCursor();
            }

            $this->db->database->commit();
            return ['success' => true, 'message' => 'Tickets reordered successfully'];

        } catch (\Exception $e) {
            $this->db->database->rollBack();
            return ['success' => false, 'message' => 'Failed to reorder tickets'];
        }
    }

    /**
     * Get user's assigned tickets
     */
    public function getUserTickets(int $userId, ?int $projectId = null): array
    {
        $sql = "
            SELECT 
                t.*,
                p.name as project_name,
                e.name as epic_name,
                s.name as sprint_name
            FROM zp_tickets t
            JOIN zp_projects p ON t.projectId = p.id
            LEFT JOIN zp_epics e ON t.epic_id = e.id
            LEFT JOIN zp_sprints s ON t.sprint_id = s.id
            WHERE t.assignee_id = :user_id
        ";

        if ($projectId) {
            $sql .= " AND t.projectId = :project_id";
        }

        $sql .= " ORDER BY t.priority DESC, t.due_date ASC";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        
        if ($projectId) {
            $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return array_map([$this, 'formatTicketForKanban'], $tickets);
    }

    // Private helper methods
    private function getTicket(int $ticketId): ?array
    {
        $sql = "SELECT * FROM zp_tickets WHERE id = :id";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':id', $ticketId, PDO::PARAM_INT);
        $stmt->execute();
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $ticket ?: null;
    }

    private function formatTicketForKanban(array $ticket): array
    {
        return [
            'id' => $ticket['id'],
            'title' => $ticket['headline'],
            'description' => $ticket['description'] ?? '',
            'priority' => $ticket['priority'] ?? 'medium',
            'story_points' => $ticket['story_points'],
            'estimated_hours' => $ticket['estimated_hours'],
            'actual_hours' => $ticket['actual_hours'],
            'due_date' => $ticket['due_date'],
            'labels' => $ticket['labels'] ? json_decode($ticket['labels'], true) : [],
            'assignee' => [
                'id' => $ticket['assignee_id'],
                'name' => trim(($ticket['assignee_firstname'] ?? '') . ' ' . ($ticket['assignee_lastname'] ?? '')),
                'avatar' => $ticket['assignee_avatar'] ?? null
            ],
            'reporter' => [
                'id' => $ticket['reporter_id'],
                'name' => trim(($ticket['reporter_firstname'] ?? '') . ' ' . ($ticket['reporter_lastname'] ?? ''))
            ],
            'epic' => [
                'id' => $ticket['epic_id'],
                'name' => $ticket['epic_name'] ?? null,
                'status' => $ticket['epic_status'] ?? null
            ],
            'sprint' => [
                'id' => $ticket['sprint_id'],
                'name' => $ticket['sprint_name'] ?? null
            ],
            'project_id' => $ticket['projectId'],
            'project_name' => $ticket['project_name'] ?? null,
            'created_at' => $ticket['dateCreated'],
            'updated_at' => $ticket['editDate'],
            'kanban_order' => $ticket['kanban_order'] ?? 0
        ];
    }

    private function logTicketActivity(
        int $projectId, 
        int $userId, 
        string $activityType, 
        int $ticketId, 
        string $description,
        ?array $metadata = null
    ): void {
        $sql = "
            INSERT INTO zp_team_activity 
            (project_id, user_id, activity_type, entity_type, entity_id, description, metadata)
            VALUES (:project_id, :user_id, :activity_type, 'ticket', :entity_id, :description, :metadata)
        ";
        
        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':activity_type', $activityType, PDO::PARAM_STR);
        $stmt->bindValue(':entity_id', $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        $stmt->bindValue(':metadata', $metadata ? json_encode($metadata) : null, PDO::PARAM_STR);
        
        $stmt->execute();
        $stmt->closeCursor();
    }
}
