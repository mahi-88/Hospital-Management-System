<?php

namespace Leantime\Domain\Tickets\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Tickets\Services\KanbanService;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Leantime\Core\Db\Db as DbCore;
use Symfony\Component\HttpFoundation\JsonResponse;
use PDO;

/**
 * PixelForge Nexus Kanban Controller
 * 
 * Handles kanban board interface for agile task management
 */
class Kanban extends Controller
{
    private KanbanService $kanbanService;
    private ProjectRepository $projectRepository;
    private DbCore $db;

    public function __construct(KanbanService $kanbanService, ProjectRepository $projectRepository, DbCore $db)
    {
        $this->kanbanService = $kanbanService;
        $this->projectRepository = $projectRepository;
        $this->db = $db;
    }

    /**
     * Display kanban board
     */
    public function board(): void
    {
        $projectId = (int) ($_GET['projectId'] ?? session('currentProject'));
        $sprintId = !empty($_GET['sprintId']) ? (int) $_GET['sprintId'] : null;

        if (!$projectId) {
            $this->tpl->setNotification('Please select a project', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        // Check permissions
        if (!Auth::userHasPermission('view_project', $projectId)) {
            $this->tpl->setNotification('Access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/dashboard');
            return;
        }

        // Get project details
        $project = $this->projectRepository->getProject($projectId);
        if (!$project) {
            $this->tpl->setNotification('Project not found', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        // Get kanban board data
        $kanbanBoard = $this->kanbanService->getKanbanBoard($projectId, $sprintId);

        // Get available sprints
        $sprints = $this->getProjectSprints($projectId);

        // Get team members for assignment
        $teamMembers = $this->getProjectTeamMembers($projectId);

        // Check user permissions for various actions
        $permissions = [
            'can_edit_tasks' => Auth::userHasPermission('edit_task', $projectId),
            'can_create_tasks' => Auth::userHasPermission('create_task', $projectId),
            'can_assign_tasks' => Auth::userHasPermission('assign_task', $projectId),
            'can_delete_tasks' => Auth::userHasPermission('delete_task', $projectId)
        ];

        $this->tpl->assign('project', $project);
        $this->tpl->assign('kanbanBoard', $kanbanBoard);
        $this->tpl->assign('sprints', $sprints);
        $this->tpl->assign('currentSprintId', $sprintId);
        $this->tpl->assign('teamMembers', $teamMembers);
        $this->tpl->assign('permissions', $permissions);
        $this->tpl->assign('projectId', $projectId);
        
        $this->tpl->display('tickets.kanban.board');
    }

    /**
     * Update ticket status via AJAX
     */
    public function updateStatus(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $ticketId = (int) ($_POST['ticketId'] ?? 0);
        $newStatus = $_POST['newStatus'] ?? '';
        $newOrder = !empty($_POST['newOrder']) ? (int) $_POST['newOrder'] : null;

        if (!$ticketId || !$newStatus) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $result = $this->kanbanService->updateTicketStatus($ticketId, $newStatus, $newOrder);
        
        return new JsonResponse($result);
    }

    /**
     * Assign ticket via AJAX
     */
    public function assignTicket(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $ticketId = (int) ($_POST['ticketId'] ?? 0);
        $userId = (int) ($_POST['userId'] ?? 0);

        if (!$ticketId || !$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $result = $this->kanbanService->assignTicket($ticketId, $userId);
        
        return new JsonResponse($result);
    }

    /**
     * Update ticket priority via AJAX
     */
    public function updatePriority(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $ticketId = (int) ($_POST['ticketId'] ?? 0);
        $priority = $_POST['priority'] ?? '';

        if (!$ticketId || !$priority) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $result = $this->kanbanService->updateTicketPriority($ticketId, $priority);
        
        return new JsonResponse($result);
    }

    /**
     * Reorder tickets within a column via AJAX
     */
    public function reorderTickets(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $ticketIds = $_POST['ticketIds'] ?? [];
        $status = $_POST['status'] ?? '';

        if (empty($ticketIds) || !$status) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $result = $this->kanbanService->reorderTickets($ticketIds, $status);
        
        return new JsonResponse($result);
    }

    /**
     * Get ticket details via AJAX
     */
    public function getTicketDetails(): JsonResponse
    {
        $ticketId = (int) ($_GET['ticketId'] ?? 0);

        if (!$ticketId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing ticket ID'], 400);
        }

        $ticket = $this->kanbanService->getTicketDetails($ticketId);

        if (!$ticket) {
            return new JsonResponse(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        // Check permissions
        if (!Auth::userHasPermission('view_project', $ticket['projectId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        return new JsonResponse(['success' => true, 'ticket' => $ticket]);
    }

    /**
     * Get kanban board data via AJAX
     */
    public function getBoardData(): JsonResponse
    {
        $projectId = (int) ($_GET['projectId'] ?? 0);
        $sprintId = !empty($_GET['sprintId']) ? (int) $_GET['sprintId'] : null;

        if (!$projectId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing project ID'], 400);
        }

        if (!Auth::userHasPermission('view_project', $projectId)) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        $kanbanBoard = $this->kanbanService->getKanbanBoard($projectId, $sprintId);
        
        return new JsonResponse(['success' => true, 'board' => $kanbanBoard]);
    }

    /**
     * Get user's personal kanban view
     */
    public function myTasks(): void
    {
        $userId = session('userdata.id');
        $projectId = !empty($_GET['projectId']) ? (int) $_GET['projectId'] : null;

        // Get user's assigned tickets
        $myTickets = $this->kanbanService->getUserTickets($userId, $projectId);

        // Group tickets by status for personal kanban view
        $personalBoard = [
            'todo' => ['name' => 'To Do', 'color' => '#007bff', 'tickets' => []],
            'in_progress' => ['name' => 'In Progress', 'color' => '#ffc107', 'tickets' => []],
            'review' => ['name' => 'Review', 'color' => '#fd7e14', 'tickets' => []],
            'done' => ['name' => 'Done', 'color' => '#28a745', 'tickets' => []]
        ];

        foreach ($myTickets as $ticket) {
            $status = $ticket['kanban_status'] ?? 'todo';
            if (isset($personalBoard[$status])) {
                $personalBoard[$status]['tickets'][] = $ticket;
            }
        }

        // Get user's projects for filtering
        $userProjects = $this->getUserProjects($userId);

        $this->tpl->assign('personalBoard', $personalBoard);
        $this->tpl->assign('userProjects', $userProjects);
        $this->tpl->assign('selectedProjectId', $projectId);
        $this->tpl->assign('totalTickets', count($myTickets));
        
        $this->tpl->display('tickets.kanban.myTasks');
    }

    /**
     * Sprint planning view
     */
    public function sprintPlanning(): void
    {
        $projectId = (int) ($_GET['projectId'] ?? session('currentProject'));

        if (!$projectId) {
            $this->tpl->setNotification('Please select a project', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        if (!Auth::userHasPermission('manage_project_team', $projectId)) {
            $this->tpl->setNotification('Access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/dashboard');
            return;
        }

        $project = $this->projectRepository->getProject($projectId);
        $sprints = $this->getProjectSprints($projectId);
        $backlogTickets = $this->getBacklogTickets($projectId);

        $this->tpl->assign('project', $project);
        $this->tpl->assign('sprints', $sprints);
        $this->tpl->assign('backlogTickets', $backlogTickets);
        $this->tpl->assign('projectId', $projectId);
        
        $this->tpl->display('tickets.kanban.sprintPlanning');
    }

    // Private helper methods
    private function getProjectSprints(int $projectId): array
    {
        $sql = "
            SELECT s.*, 
                   COUNT(t.id) as ticket_count,
                   SUM(t.story_points) as total_points
            FROM zp_sprints s
            LEFT JOIN zp_tickets t ON s.id = t.sprint_id
            WHERE s.project_id = :project_id
            GROUP BY s.id
            ORDER BY s.start_date DESC
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $sprints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $sprints;
    }

    private function getProjectTeamMembers(int $projectId): array
    {
        $sql = "
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.profileId, r.display_name as role_name
            FROM zp_user u
            JOIN zp_user_roles ur ON u.id = ur.user_id
            JOIN zp_roles r ON ur.role_id = r.id
            WHERE ur.project_id = :project_id AND ur.is_active = 1
            ORDER BY u.firstname, u.lastname
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $members;
    }

    private function getUserProjects(int $userId): array
    {
        $sql = "
            SELECT DISTINCT p.id, p.name
            FROM zp_projects p
            JOIN zp_user_roles ur ON p.id = ur.project_id
            WHERE ur.user_id = :user_id AND ur.is_active = 1 AND p.state = 1
            ORDER BY p.name
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $projects;
    }

    private function getBacklogTickets(int $projectId): array
    {
        $sql = "
            SELECT t.*, u.firstname, u.lastname
            FROM zp_tickets t
            LEFT JOIN zp_user u ON t.assignee_id = u.id
            WHERE t.projectId = :project_id AND t.kanban_status = 'backlog'
            ORDER BY t.priority DESC, t.dateCreated ASC
        ";

        $stmt = $this->db->database->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $tickets;
    }
}
