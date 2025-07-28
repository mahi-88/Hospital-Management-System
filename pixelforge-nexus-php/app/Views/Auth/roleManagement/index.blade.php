@extends('layouts.app')

@section('content')
<div class="pageheader">
    <div class="pageicon"><i class="fa fa-shield"></i></div>
    <div class="pagetitle">
        <h5>PixelForge Nexus</h5>
        <h1>Role & Permission Management</h1>
    </div>
</div>

<div class="maincontent">
    <div class="maincontentinner">
        
        <!-- Navigation Tabs -->
        <div class="row">
            <div class="col-md-12">
                <div class="widget">
                    <div class="widgetheader">
                        <h4>RBAC Management</h4>
                    </div>
                    <div class="widgetcontent">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#roles-tab" role="tab">
                                    <i class="fa fa-users"></i> Roles
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#permissions-tab" role="tab">
                                    <i class="fa fa-key"></i> Permissions
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ BASE_URL }}/auth/roleManagement/userRoles">
                                    <i class="fa fa-user-plus"></i> User Assignments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ BASE_URL }}/auth/roleManagement/testPermissions">
                                    <i class="fa fa-bug"></i> Test Permissions
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Roles Tab -->
                            <div class="tab-pane fade show active" id="roles-tab" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4>System Roles</h4>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Role Name</th>
                                                        <th>Display Name</th>
                                                        <th>Description</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($roles as $role)
                                                    <tr>
                                                        <td><code>{{ $role['name'] }}</code></td>
                                                        <td><strong>{{ $role['display_name'] }}</strong></td>
                                                        <td>{{ $role['description'] }}</td>
                                                        <td>
                                                            <a href="{{ BASE_URL }}/auth/roleManagement/showRole?roleId={{ $role['id'] }}" 
                                                               class="btn btn-sm btn-primary">
                                                                <i class="fa fa-eye"></i> View Permissions
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Permissions Tab -->
                            <div class="tab-pane fade" id="permissions-tab" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12">
                                        <h4>System Permissions</h4>
                                        
                                        @php
                                            $permissionsByCategory = [];
                                            foreach($permissions as $permission) {
                                                $permissionsByCategory[$permission['category']][] = $permission;
                                            }
                                        @endphp

                                        @foreach($permissionsByCategory as $category => $categoryPermissions)
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <h5 class="mb-0">
                                                    <i class="fa fa-folder"></i> {{ ucfirst($category) }} Permissions
                                                </h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Permission</th>
                                                                <th>Display Name</th>
                                                                <th>Description</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($categoryPermissions as $permission)
                                                            <tr>
                                                                <td><code>{{ $permission['name'] }}</code></td>
                                                                <td>{{ $permission['display_name'] }}</td>
                                                                <td>{{ $permission['description'] }}</td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-12">
                <div class="widget">
                    <div class="widgetheader">
                        <h4>Quick Actions</h4>
                    </div>
                    <div class="widgetcontent">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="{{ BASE_URL }}/auth/roleManagement/assignRole" class="btn btn-success btn-block">
                                    <i class="fa fa-plus"></i> Assign Role to User
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ BASE_URL }}/auth/roleManagement/userRoles" class="btn btn-info btn-block">
                                    <i class="fa fa-users"></i> View User Roles
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ BASE_URL }}/auth/roleManagement/testPermissions" class="btn btn-warning btn-block">
                                    <i class="fa fa-bug"></i> Test Permissions
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="{{ BASE_URL }}/auth/roleManagement/exportRoles" class="btn btn-secondary btn-block">
                                    <i class="fa fa-download"></i> Export Configuration
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <h5><i class="fa fa-info-circle"></i> PixelForge Nexus RBAC System</h5>
                    <p>This Role-Based Access Control system provides granular permission management for your game development team. 
                    Each role has specific permissions that control what users can see and do within the system.</p>
                    <ul>
                        <li><strong>Super Admin:</strong> Full system access and user management</li>
                        <li><strong>Project Admin:</strong> Project and team management within assigned projects</li>
                        <li><strong>Developer:</strong> Code access, task management, and documentation</li>
                        <li><strong>Designer:</strong> Asset management and creative content control</li>
                        <li><strong>QA Engineer:</strong> Testing tools and quality assurance features</li>
                        <li><strong>Client:</strong> Read-only access to approved project content</li>
                        <li><strong>Guest:</strong> Minimal preview access</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-refresh permission tests every 30 seconds if on test page
    if (window.location.href.includes('testPermissions')) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
});
</script>
@endsection
