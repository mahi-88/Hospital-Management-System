@extends('layouts.app')

@section('content')
<div class="pageheader">
    <div class="pageicon"><i class="fa fa-folder-open"></i></div>
    <div class="pagetitle">
        <h5>{{ $project['name'] }}</h5>
        <h1>Document & Asset Manager</h1>
    </div>
    <div class="pageactions">
        @if($permissions['can_upload'])
        <button class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">
            <i class="fa fa-upload"></i> Upload Files
        </button>
        @endif
        @if($permissions['can_create_folder'])
        <button class="btn btn-secondary" data-toggle="modal" data-target="#createFolderModal">
            <i class="fa fa-folder-plus"></i> New Folder
        </button>
        @endif
    </div>
</div>

<div class="maincontent">
    <div class="maincontentinner">
        
        <!-- Document Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="widget stats-widget">
                    <div class="stats-content">
                        <div class="stats-number">{{ $stats['total_documents'] ?? 0 }}</div>
                        <div class="stats-label">Total Documents</div>
                    </div>
                    <div class="stats-icon">
                        <i class="fa fa-file"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget stats-widget">
                    <div class="stats-content">
                        <div class="stats-number">{{ formatFileSize($stats['total_size'] ?? 0) }}</div>
                        <div class="stats-label">Total Size</div>
                    </div>
                    <div class="stats-icon">
                        <i class="fa fa-hdd"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget stats-widget">
                    <div class="stats-content">
                        <div class="stats-number">{{ count($stats['by_type'] ?? []) }}</div>
                        <div class="stats-label">File Types</div>
                    </div>
                    <div class="stats-icon">
                        <i class="fa fa-tags"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="widget stats-widget">
                    <div class="stats-content">
                        <div class="stats-number">{{ count($stats['recent_uploads'] ?? []) }}</div>
                        <div class="stats-label">Recent Uploads</div>
                    </div>
                    <div class="stats-icon">
                        <i class="fa fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="row">
            <div class="col-md-12">
                <div class="widget">
                    <div class="widgetheader">
                        <h4>Document Library</h4>
                        <div class="widget-actions">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary view-mode-btn active" data-view="grid">
                                    <i class="fa fa-th"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary view-mode-btn" data-view="list">
                                    <i class="fa fa-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="widgetcontent">
                        
                        <!-- Search and Filter Bar -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search documents...">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="document" {{ $currentType == 'document' ? 'selected' : '' }}>Documents</option>
                                    <option value="image" {{ $currentType == 'image' ? 'selected' : '' }}>Images</option>
                                    <option value="video" {{ $currentType == 'video' ? 'selected' : '' }}>Videos</option>
                                    <option value="audio" {{ $currentType == 'audio' ? 'selected' : '' }}>Audio</option>
                                    <option value="archive" {{ $currentType == 'archive' ? 'selected' : '' }}>Archives</option>
                                    <option value="code" {{ $currentType == 'code' ? 'selected' : '' }}>Code</option>
                                    <option value="design" {{ $currentType == 'design' ? 'selected' : '' }}>Design</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="folderFilter">
                                    <option value="">All Folders</option>
                                    @foreach($folders as $folder)
                                    <option value="{{ $folder['id'] }}" {{ $currentFolderId == $folder['id'] ? 'selected' : '' }}>
                                        {{ $folder['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="pending_approval">Pending Approval</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary btn-block" id="clearFilters">
                                    <i class="fa fa-times"></i> Clear
                                </button>
                            </div>
                        </div>

                        <!-- Folder Navigation -->
                        @if(!empty($folders))
                        <div class="folder-navigation mb-3">
                            <div class="row">
                                @foreach($folders as $folder)
                                <div class="col-md-2 col-sm-3 col-6 mb-2">
                                    <a href="?projectId={{ $projectId }}&folderId={{ $folder['id'] }}" class="folder-link">
                                        <div class="folder-item">
                                            <i class="fa fa-folder" style="color: {{ $folder['color'] ?? '#007bff' }}"></i>
                                            <span class="folder-name">{{ $folder['name'] }}</span>
                                        </div>
                                    </a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Document Grid/List View -->
                        <div id="documentsContainer" class="documents-grid">
                            @if(empty($documents))
                            <div class="empty-state text-center py-5">
                                <i class="fa fa-folder-open fa-3x text-muted mb-3"></i>
                                <h4>No documents found</h4>
                                <p class="text-muted">Upload your first document to get started</p>
                                @if($permissions['can_upload'])
                                <button class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">
                                    <i class="fa fa-upload"></i> Upload Files
                                </button>
                                @endif
                            </div>
                            @else
                            <div class="row" id="documentsGrid">
                                @foreach($documents as $document)
                                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3 document-item" 
                                     data-type="{{ $document['document_type'] }}" 
                                     data-status="{{ $document['status'] }}"
                                     data-folder="{{ $document['folder_id'] ?? '' }}">
                                    <div class="document-card">
                                        <div class="document-thumbnail">
                                            @if($document['thumbnail_url'])
                                            <img src="{{ $document['thumbnail_url'] }}" alt="{{ $document['original_name'] }}" class="img-fluid">
                                            @else
                                            <div class="file-icon">
                                                <i class="fa {{ getFileIcon($document['document_type'], $document['file_extension']) }} fa-2x"></i>
                                            </div>
                                            @endif
                                            
                                            <!-- Status Badge -->
                                            @if($document['approval_status'] !== 'not_required')
                                            <div class="status-badge status-{{ $document['approval_status'] }}">
                                                {{ ucfirst(str_replace('_', ' ', $document['approval_status'])) }}
                                            </div>
                                            @endif

                                            <!-- Actions Overlay -->
                                            <div class="document-actions">
                                                <a href="/documents/preview?id={{ $document['id'] }}" class="btn btn-sm btn-primary" title="Preview">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <a href="/documents/download?id={{ $document['id'] }}" class="btn btn-sm btn-success" title="Download">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                                @if($document['can_edit'])
                                                <button class="btn btn-sm btn-warning edit-document-btn" data-id="{{ $document['id'] }}" title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                @endif
                                                @if($document['can_delete'])
                                                <button class="btn btn-sm btn-danger delete-document-btn" data-id="{{ $document['id'] }}" title="Delete">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <div class="document-info">
                                            <h6 class="document-title" title="{{ $document['original_name'] }}">
                                                {{ Str::limit($document['original_name'], 20) }}
                                            </h6>
                                            <div class="document-meta">
                                                <small class="text-muted">
                                                    {{ formatFileSize($document['file_size']) }} • 
                                                    {{ $document['uploader_firstname'] }} {{ $document['uploader_lastname'] }} • 
                                                    {{ date('M j, Y', strtotime($document['created_at'])) }}
                                                </small>
                                            </div>
                                            
                                            @if(!empty($document['tags']))
                                            <div class="document-tags mt-1">
                                                @foreach(array_slice($document['tags'], 0, 2) as $tag)
                                                <span class="badge badge-secondary badge-sm">{{ $tag }}</span>
                                                @endforeach
                                                @if(count($document['tags']) > 2)
                                                <span class="badge badge-light badge-sm">+{{ count($document['tags']) - 2 }}</span>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>

                            <!-- Pagination -->
                            @if($totalPages > 1)
                            <div class="row">
                                <div class="col-md-12">
                                    <nav aria-label="Documents pagination">
                                        <ul class="pagination justify-content-center">
                                            @if($currentPage > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="?projectId={{ $projectId }}&page={{ $currentPage - 1 }}&type={{ $currentType }}&folderId={{ $currentFolderId }}">Previous</a>
                                            </li>
                                            @endif
                                            
                                            @for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                            <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                                <a class="page-link" href="?projectId={{ $projectId }}&page={{ $i }}&type={{ $currentType }}&folderId={{ $currentFolderId }}">{{ $i }}</a>
                                            </li>
                                            @endfor
                                            
                                            @if($currentPage < $totalPages)
                                            <li class="page-item">
                                                <a class="page-link" href="?projectId={{ $projectId }}&page={{ $currentPage + 1 }}&type={{ $currentType }}&folderId={{ $currentFolderId }}">Next</a>
                                            </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                            @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
@if($permissions['can_upload'])
<div class="modal fade" id="uploadModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Documents</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="projectId" value="{{ $projectId }}">
                    
                    <div class="form-group">
                        <label>Select Files</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="fileInput" name="file" multiple>
                            <label class="custom-file-label" for="fileInput">Choose files...</label>
                        </div>
                        <small class="form-text text-muted">
                            Maximum file size: 100MB. Supported formats: PDF, DOC, XLS, PPT, Images, Videos, Archives
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Folder</label>
                        <select class="form-control" name="folderId">
                            <option value="">Root Folder</option>
                            @foreach($folders as $folder)
                            <option value="{{ $folder['id'] }}">{{ $folder['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category">
                            <option value="">Select Category</option>
                            <option value="concept_art">Concept Art</option>
                            <option value="documentation">Documentation</option>
                            <option value="assets">Game Assets</option>
                            <option value="audio">Audio Files</option>
                            <option value="video">Video Files</option>
                            <option value="code">Source Code</option>
                            <option value="marketing">Marketing Materials</option>
                            <option value="legal">Legal Documents</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Optional description..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Tags</label>
                        <input type="text" class="form-control" name="tags" placeholder="Enter tags separated by commas">
                        <small class="form-text text-muted">e.g., character, environment, ui, prototype</small>
                    </div>
                </form>

                <div id="uploadProgress" class="mt-3" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="upload-status mt-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    <i class="fa fa-upload"></i> Upload Files
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Create Folder Modal -->
@if($permissions['can_create_folder'])
<div class="modal fade" id="createFolderModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Folder</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createFolderForm">
                    <input type="hidden" name="projectId" value="{{ $projectId }}">
                    
                    <div class="form-group">
                        <label>Folder Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>Parent Folder</label>
                        <select class="form-control" name="parentFolderId">
                            <option value="">Root Folder</option>
                            @foreach($folders as $folder)
                            <option value="{{ $folder['id'] }}">{{ $folder['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" class="form-control" name="color" value="#007bff">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createFolderBtn">
                    <i class="fa fa-folder-plus"></i> Create Folder
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<style>
.stats-widget {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.stats-content {
    position: relative;
    z-index: 2;
}

.stats-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stats-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.stats-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3rem;
    opacity: 0.3;
}

.document-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
}

.document-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.document-thumbnail {
    position: relative;
    height: 120px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.document-thumbnail img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}

.file-icon {
    color: #6c757d;
}

.document-actions {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.document-card:hover .document-actions {
    opacity: 1;
}

.status-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending { background: #ffc107; color: #000; }
.status-approved { background: #28a745; color: #fff; }
.status-rejected { background: #dc3545; color: #fff; }

.document-info {
    padding: 10px;
}

.document-title {
    margin: 0 0 5px 0;
    font-size: 0.9rem;
    font-weight: 600;
}

.document-meta {
    font-size: 0.75rem;
    line-height: 1.2;
}

.document-tags .badge {
    font-size: 0.65rem;
    margin-right: 2px;
}

.folder-item {
    text-align: center;
    padding: 15px 10px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.folder-item:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.folder-item i {
    font-size: 2rem;
    margin-bottom: 8px;
    display: block;
}

.folder-name {
    font-size: 0.9rem;
    font-weight: 500;
}

.empty-state {
    color: #6c757d;
}

.view-mode-btn.active {
    background-color: #007bff;
    color: white;
}

@media (max-width: 768px) {
    .document-card {
        margin-bottom: 15px;
    }
    
    .stats-widget {
        margin-bottom: 15px;
    }
}
</style>

<script>
$(document).ready(function() {
    // File upload handling
    $('#uploadBtn').click(function() {
        const formData = new FormData($('#uploadForm')[0]);
        const files = $('#fileInput')[0].files;
        
        if (files.length === 0) {
            alert('Please select files to upload');
            return;
        }
        
        $('#uploadProgress').show();
        $('.upload-status').text('Uploading...');
        
        $.ajax({
            url: '/documents/upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        $('.progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $('.upload-status').text('Upload completed successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $('.upload-status').text('Upload failed: ' + response.message);
                }
            },
            error: function() {
                $('.upload-status').text('Upload failed. Please try again.');
            }
        });
    });
    
    // Create folder handling
    $('#createFolderBtn').click(function() {
        const formData = $('#createFolderForm').serialize();
        
        $.ajax({
            url: '/documents/createFolder',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to create folder: ' + response.message);
                }
            },
            error: function() {
                alert('Failed to create folder. Please try again.');
            }
        });
    });
    
    // Delete document handling
    $('.delete-document-btn').click(function() {
        const documentId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this document?')) {
            $.ajax({
                url: '/documents/delete',
                type: 'POST',
                data: { documentId: documentId },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete document: ' + response.message);
                    }
                },
                error: function() {
                    alert('Failed to delete document. Please try again.');
                }
            });
        }
    });
    
    // View mode toggle
    $('.view-mode-btn').click(function() {
        $('.view-mode-btn').removeClass('active');
        $(this).addClass('active');
        
        const viewMode = $(this).data('view');
        if (viewMode === 'list') {
            $('#documentsGrid').removeClass('row').addClass('list-view');
        } else {
            $('#documentsGrid').removeClass('list-view').addClass('row');
        }
    });
    
    // Filter handling
    $('#typeFilter, #folderFilter, #statusFilter').change(function() {
        updateFilters();
    });
    
    $('#searchBtn').click(function() {
        performSearch();
    });
    
    $('#searchInput').keypress(function(e) {
        if (e.which === 13) {
            performSearch();
        }
    });
    
    $('#clearFilters').click(function() {
        $('#typeFilter, #folderFilter, #statusFilter').val('');
        $('#searchInput').val('');
        updateFilters();
    });
    
    function updateFilters() {
        const params = new URLSearchParams(window.location.search);
        params.set('projectId', {{ $projectId }});
        params.set('type', $('#typeFilter').val());
        params.set('folderId', $('#folderFilter').val());
        params.set('status', $('#statusFilter').val());
        params.delete('page'); // Reset to first page
        
        window.location.search = params.toString();
    }
    
    function performSearch() {
        const query = $('#searchInput').val().trim();
        if (query) {
            window.location.href = '/documents/search?projectId={{ $projectId }}&q=' + encodeURIComponent(query);
        }
    }
    
    // File input label update
    $('#fileInput').change(function() {
        const files = this.files;
        if (files.length === 1) {
            $('.custom-file-label').text(files[0].name);
        } else if (files.length > 1) {
            $('.custom-file-label').text(files.length + ' files selected');
        } else {
            $('.custom-file-label').text('Choose files...');
        }
    });
});

// Helper function for file icons
function getFileIcon(type, extension) {
    const iconMap = {
        'image': 'fa-image',
        'video': 'fa-video',
        'audio': 'fa-music',
        'document': 'fa-file-text',
        'archive': 'fa-file-archive',
        'code': 'fa-code',
        'design': 'fa-paint-brush'
    };
    
    return iconMap[type] || 'fa-file';
}

// Helper function for file size formatting
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>
@endsection
