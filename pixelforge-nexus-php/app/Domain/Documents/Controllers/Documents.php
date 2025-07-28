<?php

namespace Leantime\Domain\Documents\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Documents\Services\DocumentService;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * PixelForge Nexus Documents Controller
 * 
 * Handles document upload, management, and access control
 */
class Documents extends Controller
{
    private DocumentService $documentService;
    private ProjectRepository $projectRepository;

    public function __construct(DocumentService $documentService, ProjectRepository $projectRepository)
    {
        $this->documentService = $documentService;
        $this->projectRepository = $projectRepository;
    }

    /**
     * Display document manager interface
     */
    public function index(): void
    {
        $projectId = (int) ($_GET['projectId'] ?? session('currentProject'));
        $folderId = !empty($_GET['folderId']) ? (int) $_GET['folderId'] : null;
        $documentType = $_GET['type'] ?? null;
        $page = (int) ($_GET['page'] ?? 1);

        if (!$projectId) {
            $this->tpl->setNotification('Please select a project', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        // Check permissions
        if (!Auth::userHasPermission('view_document', $projectId)) {
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

        // Get documents
        $userId = session('userdata.id');
        $documentsData = $this->documentService->getProjectDocuments(
            $projectId, 
            $userId, 
            $folderId, 
            $documentType, 
            null, 
            $page
        );

        // Get folders
        $folders = $this->getProjectFolders($projectId);

        // Get document statistics
        $stats = $this->getDocumentStatistics($projectId, $userId);

        // Check user permissions
        $permissions = [
            'can_upload' => Auth::userHasPermission('upload_document', $projectId),
            'can_create_folder' => Auth::userHasPermission('manage_document_folders', $projectId),
            'can_approve' => Auth::userHasPermission('approve_document', $projectId),
            'can_share' => Auth::userHasPermission('share_document', $projectId)
        ];

        $this->tpl->assign('project', $project);
        $this->tpl->assign('documents', $documentsData['documents']);
        $this->tpl->assign('totalDocuments', $documentsData['total']);
        $this->tpl->assign('totalPages', $documentsData['pages']);
        $this->tpl->assign('currentPage', $documentsData['current_page']);
        $this->tpl->assign('folders', $folders);
        $this->tpl->assign('currentFolderId', $folderId);
        $this->tpl->assign('currentType', $documentType);
        $this->tpl->assign('stats', $stats);
        $this->tpl->assign('permissions', $permissions);
        $this->tpl->assign('projectId', $projectId);

        $this->tpl->display('documents.index');
    }

    /**
     * Upload document via AJAX
     */
    public function upload(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $projectId = (int) ($_POST['projectId'] ?? 0);
        $folderId = !empty($_POST['folderId']) ? (int) $_POST['folderId'] : null;
        $description = $_POST['description'] ?? null;
        $category = $_POST['category'] ?? null;
        $tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : null;

        if (!$projectId) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID is required'], 400);
        }

        if (empty($_FILES['file'])) {
            return new JsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->uploadDocument(
            $_FILES['file'],
            $projectId,
            $userId,
            $folderId,
            $description,
            $tags,
            $category
        );

        return new JsonResponse($result);
    }

    /**
     * Download document
     */
    public function download(): BinaryFileResponse|JsonResponse
    {
        $documentId = (int) ($_GET['id'] ?? 0);

        if (!$documentId) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID is required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->downloadDocument($documentId, $userId);

        if (!$result['success']) {
            return new JsonResponse($result, 403);
        }

        $response = new BinaryFileResponse($result['file_path']);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $result['file_name']
        );
        $response->headers->set('Content-Type', $result['mime_type']);

        return $response;
    }

    /**
     * Preview document
     */
    public function preview(): void
    {
        $documentId = (int) ($_GET['id'] ?? 0);

        if (!$documentId) {
            $this->tpl->setNotification('Document ID is required', 'error');
            $this->tpl->redirect(BASE_URL . '/documents');
            return;
        }

        $userId = session('userdata.id');
        $document = $this->documentService->getDocument($documentId, $userId);

        if (!$document) {
            $this->tpl->setNotification('Document not found or access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/documents');
            return;
        }

        // Log view access
        $this->documentService->logDocumentAccess($documentId, $userId, 'view');

        // Get document comments
        $comments = $this->getDocumentComments($documentId);

        // Check permissions
        $permissions = [
            'can_edit' => $document['can_edit'],
            'can_delete' => $document['can_delete'],
            'can_comment' => Auth::userHasPermission('comment_on_document', $document['project_id']),
            'can_approve' => Auth::userHasPermission('approve_document', $document['project_id']),
            'can_share' => Auth::userHasPermission('share_document', $document['project_id'])
        ];

        $this->tpl->assign('document', $document);
        $this->tpl->assign('comments', $comments);
        $this->tpl->assign('permissions', $permissions);
        $this->tpl->assign('previewUrl', $this->getPreviewUrl($document));

        $this->tpl->display('documents.preview');
    }

    /**
     * Delete document via AJAX
     */
    public function delete(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $documentId = (int) ($_POST['documentId'] ?? 0);

        if (!$documentId) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID is required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->deleteDocument($documentId, $userId);

        return new JsonResponse($result);
    }

    /**
     * Approve document via AJAX
     */
    public function approve(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $documentId = (int) ($_POST['documentId'] ?? 0);
        $approvalNotes = $_POST['notes'] ?? null;

        if (!$documentId) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID is required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->approveDocument($documentId, $userId, $approvalNotes);

        return new JsonResponse($result);
    }

    /**
     * Reject document via AJAX
     */
    public function reject(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $documentId = (int) ($_POST['documentId'] ?? 0);
        $rejectionNotes = $_POST['notes'] ?? '';

        if (!$documentId) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID is required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->rejectDocument($documentId, $userId, $rejectionNotes);

        return new JsonResponse($result);
    }

    /**
     * Add comment to document via AJAX
     */
    public function addComment(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $documentId = (int) ($_POST['documentId'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $commentType = $_POST['type'] ?? 'general';
        $parentCommentId = !empty($_POST['parentCommentId']) ? (int) $_POST['parentCommentId'] : null;

        if (!$documentId || !$comment) {
            return new JsonResponse(['success' => false, 'message' => 'Document ID and comment are required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->addComment($documentId, $userId, $comment, $commentType, $parentCommentId);

        return new JsonResponse($result);
    }

    /**
     * Create folder via AJAX
     */
    public function createFolder(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $projectId = (int) ($_POST['projectId'] ?? 0);
        $folderName = trim($_POST['name'] ?? '');
        $parentFolderId = !empty($_POST['parentFolderId']) ? (int) $_POST['parentFolderId'] : null;
        $description = $_POST['description'] ?? null;
        $color = $_POST['color'] ?? '#007bff';

        if (!$projectId || !$folderName) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID and folder name are required'], 400);
        }

        if (!Auth::userHasPermission('manage_document_folders', $projectId)) {
            return new JsonResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        $userId = session('userdata.id');
        $result = $this->documentService->createFolder($projectId, $folderName, $parentFolderId, $description, $color, $userId);

        return new JsonResponse($result);
    }

    /**
     * Get document analytics via AJAX
     */
    public function analytics(): JsonResponse
    {
        $projectId = (int) ($_GET['projectId'] ?? 0);

        if (!$projectId) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID is required'], 400);
        }

        if (!Auth::userHasPermission('view_document_analytics', $projectId)) {
            return new JsonResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        $userId = session('userdata.id');
        $analytics = $this->documentService->getDocumentAnalytics($projectId, $userId);

        return new JsonResponse(['success' => true, 'analytics' => $analytics]);
    }

    /**
     * Search documents via AJAX
     */
    public function search(): JsonResponse
    {
        $projectId = (int) ($_GET['projectId'] ?? 0);
        $query = trim($_GET['q'] ?? '');
        $page = (int) ($_GET['page'] ?? 1);

        if (!$projectId || !$query) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID and search query are required'], 400);
        }

        if (!Auth::userHasPermission('view_document', $projectId)) {
            return new JsonResponse(['success' => false, 'message' => 'Insufficient permissions'], 403);
        }

        $userId = session('userdata.id');
        $results = $this->documentService->searchDocuments($projectId, $userId, $query, $page);

        return new JsonResponse(['success' => true, 'results' => $results]);
    }

    // Private helper methods
    private function getProjectFolders(int $projectId): array
    {
        // This would be implemented in the DocumentService
        return [];
    }

    private function getDocumentStatistics(int $projectId, int $userId): array
    {
        // This would be implemented in the DocumentService
        return [
            'total_documents' => 0,
            'total_size' => 0,
            'by_type' => [],
            'recent_uploads' => []
        ];
    }

    private function getDocumentComments(int $documentId): array
    {
        // This would be implemented in the DocumentService
        return [];
    }

    private function getPreviewUrl(array $document): ?string
    {
        // Generate preview URL based on document type
        $documentType = $document['document_type'];
        $documentId = $document['id'];

        switch ($documentType) {
            case 'image':
                return "/documents/preview/image/{$documentId}";
            case 'video':
                return "/documents/preview/video/{$documentId}";
            case 'document':
                if ($document['mime_type'] === 'application/pdf') {
                    return "/documents/preview/pdf/{$documentId}";
                }
                break;
        }

        return null;
    }
}
