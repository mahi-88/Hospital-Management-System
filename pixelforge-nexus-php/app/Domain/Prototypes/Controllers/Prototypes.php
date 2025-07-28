<?php

namespace Leantime\Domain\Prototypes\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Prototypes\Services\PrototypeService;
use Leantime\Domain\Prototypes\Services\VideoSubmissionService;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * PixelForge Nexus Prototypes Controller
 * 
 * Handles Figma prototypes and design submissions
 */
class Prototypes extends Controller
{
    private PrototypeService $prototypeService;
    private VideoSubmissionService $videoService;
    private ProjectRepository $projectRepository;

    public function __construct(
        PrototypeService $prototypeService, 
        VideoSubmissionService $videoService,
        ProjectRepository $projectRepository
    ) {
        $this->prototypeService = $prototypeService;
        $this->videoService = $videoService;
        $this->projectRepository = $projectRepository;
    }

    /**
     * Display prototypes and videos gallery
     */
    public function index(): void
    {
        $projectId = (int) ($_GET['projectId'] ?? session('currentProject'));
        $type = $_GET['type'] ?? 'all'; // all, prototypes, videos
        $status = $_GET['status'] ?? null;
        $page = (int) ($_GET['page'] ?? 1);

        if (!$projectId) {
            $this->tpl->setNotification('Please select a project', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        // Check permissions
        if (!Auth::userHasPermission('view_prototype', $projectId) && !Auth::userHasPermission('view_video', $projectId)) {
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

        $userId = session('userdata.id');
        $prototypes = [];
        $videos = [];

        // Get prototypes if requested
        if ($type === 'all' || $type === 'prototypes') {
            if (Auth::userHasPermission('view_prototype', $projectId)) {
                $prototypesData = $this->prototypeService->getProjectPrototypes(
                    $projectId, $userId, $status, null, null, $page
                );
                $prototypes = $prototypesData['prototypes'];
            }
        }

        // Get videos if requested
        if ($type === 'all' || $type === 'videos') {
            if (Auth::userHasPermission('view_video', $projectId)) {
                $videosData = $this->videoService->getProjectVideos(
                    $projectId, $userId, $status, null, null, $page
                );
                $videos = $videosData['videos'];
            }
        }

        // Get submission statistics
        $stats = $this->getSubmissionStatistics($projectId, $userId);

        // Check user permissions
        $permissions = [
            'can_submit_prototype' => Auth::userHasPermission('submit_prototype', $projectId),
            'can_submit_video' => Auth::userHasPermission('submit_video', $projectId),
            'can_review' => Auth::userHasPermission('review_prototype', $projectId) || Auth::userHasPermission('review_video', $projectId),
            'can_manage_collections' => Auth::userHasPermission('manage_submission_collections', $projectId)
        ];

        $this->tpl->assign('project', $project);
        $this->tpl->assign('prototypes', $prototypes);
        $this->tpl->assign('videos', $videos);
        $this->tpl->assign('currentType', $type);
        $this->tpl->assign('currentStatus', $status);
        $this->tpl->assign('stats', $stats);
        $this->tpl->assign('permissions', $permissions);
        $this->tpl->assign('projectId', $projectId);

        $this->tpl->display('prototypes.index');
    }

    /**
     * Submit prototype via AJAX
     */
    public function submitPrototype(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $projectId = (int) ($_POST['projectId'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $figmaUrl = trim($_POST['figmaUrl'] ?? '');
        $description = $_POST['description'] ?? null;
        $prototypeType = $_POST['prototypeType'] ?? 'figma';
        $version = $_POST['version'] ?? null;
        $visibility = $_POST['visibility'] ?? 'team';
        $tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : [];

        if (!$projectId || !$title || !$figmaUrl) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID, title, and Figma URL are required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->prototypeService->submitPrototype(
            $projectId, $userId, $title, $figmaUrl, $description, 
            $prototypeType, $version, $tags, $visibility
        );

        return new JsonResponse($result);
    }

    /**
     * Submit video via AJAX
     */
    public function submitVideo(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $projectId = (int) ($_POST['projectId'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $videoUrl = trim($_POST['videoUrl'] ?? '');
        $description = $_POST['description'] ?? null;
        $submissionType = $_POST['submissionType'] ?? 'progress_update';
        $visibility = $_POST['visibility'] ?? 'team';
        $tags = !empty($_POST['tags']) ? explode(',', $_POST['tags']) : [];

        if (!$projectId || !$title || !$videoUrl) {
            return new JsonResponse(['success' => false, 'message' => 'Project ID, title, and video URL are required'], 400);
        }

        $userId = session('userdata.id');
        $result = $this->videoService->submitVideo(
            $projectId, $userId, $title, $videoUrl, $description, 
            $submissionType, $tags, $visibility
        );

        return new JsonResponse($result);
    }

    /**
     * View prototype details
     */
    public function viewPrototype(): void
    {
        $prototypeId = (int) ($_GET['id'] ?? 0);

        if (!$prototypeId) {
            $this->tpl->setNotification('Prototype ID is required', 'error');
            $this->tpl->redirect(BASE_URL . '/prototypes');
            return;
        }

        $userId = session('userdata.id');
        $prototype = $this->prototypeService->getPrototype($prototypeId, $userId);

        if (!$prototype) {
            $this->tpl->setNotification('Prototype not found or access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/prototypes');
            return;
        }

        // Get prototype reviews
        $reviews = $this->prototypeService->getPrototypeReviews($prototypeId);

        // Check permissions
        $permissions = [
            'can_edit' => $prototype['can_edit'],
            'can_delete' => $prototype['can_delete'],
            'can_review' => $prototype['can_review']
        ];

        $this->tpl->assign('prototype', $prototype);
        $this->tpl->assign('reviews', $reviews);
        $this->tpl->assign('permissions', $permissions);

        $this->tpl->display('prototypes.viewPrototype');
    }

    /**
     * View video details
     */
    public function viewVideo(): void
    {
        $videoId = (int) ($_GET['id'] ?? 0);

        if (!$videoId) {
            $this->tpl->setNotification('Video ID is required', 'error');
            $this->tpl->redirect(BASE_URL . '/prototypes');
            return;
        }

        $userId = session('userdata.id');
        $video = $this->videoService->getVideo($videoId, $userId);

        if (!$video) {
            $this->tpl->setNotification('Video not found or access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/prototypes');
            return;
        }

        // Get video reviews
        $reviews = $this->getVideoReviews($videoId);

        // Check permissions
        $permissions = [
            'can_edit' => $video['can_edit'],
            'can_delete' => $video['can_delete'],
            'can_review' => $video['can_review']
        ];

        $this->tpl->assign('video', $video);
        $this->tpl->assign('reviews', $reviews);
        $this->tpl->assign('permissions', $permissions);

        $this->tpl->display('prototypes.viewVideo');
    }

    /**
     * Review submission via AJAX
     */
    public function reviewSubmission(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $submissionType = $_POST['submissionType'] ?? '';
        $submissionId = (int) ($_POST['submissionId'] ?? 0);
        $reviewStatus = $_POST['reviewStatus'] ?? '';
        $comment = $_POST['comment'] ?? null;
        $rating = !empty($_POST['rating']) ? (int) $_POST['rating'] : null;

        if (!$submissionType || !$submissionId || !$reviewStatus) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $userId = session('userdata.id');

        if ($submissionType === 'prototype') {
            $result = $this->prototypeService->reviewPrototype($submissionId, $userId, $reviewStatus, $comment, $rating);
        } elseif ($submissionType === 'video') {
            $result = $this->videoService->reviewVideo($submissionId, $userId, $reviewStatus, $comment, $rating);
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Invalid submission type'], 400);
        }

        return new JsonResponse($result);
    }

    /**
     * Add reaction via AJAX
     */
    public function addReaction(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $submissionType = $_POST['submissionType'] ?? '';
        $submissionId = (int) ($_POST['submissionId'] ?? 0);
        $reactionType = $_POST['reactionType'] ?? 'like';

        if (!$submissionType || !$submissionId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $userId = session('userdata.id');

        if ($submissionType === 'prototype') {
            $result = $this->prototypeService->addReaction($submissionId, $userId, $reactionType);
        } else {
            // Video reactions would be implemented similarly
            $result = ['success' => false, 'message' => 'Video reactions not yet implemented'];
        }

        return new JsonResponse($result);
    }

    /**
     * Delete submission via AJAX
     */
    public function deleteSubmission(): JsonResponse
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
        }

        $submissionType = $_POST['submissionType'] ?? '';
        $submissionId = (int) ($_POST['submissionId'] ?? 0);

        if (!$submissionType || !$submissionId) {
            return new JsonResponse(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        $userId = session('userdata.id');

        if ($submissionType === 'prototype') {
            $result = $this->prototypeService->deletePrototype($submissionId, $userId);
        } elseif ($submissionType === 'video') {
            // Video deletion would be implemented in VideoSubmissionService
            $result = ['success' => false, 'message' => 'Video deletion not yet implemented'];
        } else {
            return new JsonResponse(['success' => false, 'message' => 'Invalid submission type'], 400);
        }

        return new JsonResponse($result);
    }

    /**
     * Review panel for admins
     */
    public function reviewPanel(): void
    {
        $projectId = (int) ($_GET['projectId'] ?? session('currentProject'));

        if (!$projectId) {
            $this->tpl->setNotification('Please select a project', 'error');
            $this->tpl->redirect(BASE_URL . '/projects');
            return;
        }

        // Check review permissions
        if (!Auth::userHasPermission('review_prototype', $projectId) && !Auth::userHasPermission('review_video', $projectId)) {
            $this->tpl->setNotification('Access denied', 'error');
            $this->tpl->redirect(BASE_URL . '/dashboard');
            return;
        }

        $userId = session('userdata.id');

        // Get pending submissions
        $pendingPrototypes = [];
        $pendingVideos = [];

        if (Auth::userHasPermission('review_prototype', $projectId)) {
            $prototypesData = $this->prototypeService->getProjectPrototypes($projectId, $userId, 'submitted');
            $pendingPrototypes = $prototypesData['prototypes'];
        }

        if (Auth::userHasPermission('review_video', $projectId)) {
            $videosData = $this->videoService->getProjectVideos($projectId, $userId, 'submitted');
            $pendingVideos = $videosData['videos'];
        }

        $this->tpl->assign('pendingPrototypes', $pendingPrototypes);
        $this->tpl->assign('pendingVideos', $pendingVideos);
        $this->tpl->assign('projectId', $projectId);

        $this->tpl->display('prototypes.reviewPanel');
    }

    // Private helper methods
    private function getSubmissionStatistics(int $projectId, int $userId): array
    {
        // This would be implemented to get submission statistics
        return [
            'total_prototypes' => 0,
            'total_videos' => 0,
            'pending_reviews' => 0,
            'approved_submissions' => 0
        ];
    }

    private function getVideoReviews(int $videoId): array
    {
        // This would be implemented similar to prototype reviews
        return [];
    }
}
