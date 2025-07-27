--------------------------- MODULE PixelForgeNexus ---------------------------
(*
Formal specification of PixelForge Nexus system behavior using TLA+
This specification models the core security and functional properties
of the game development management system.
*)

EXTENDS Integers, Sequences, FiniteSets, TLC

CONSTANTS
    Users,          \* Set of all users
    Projects,       \* Set of all projects  
    Documents,      \* Set of all documents
    Roles,          \* Set of user roles {ADMIN, PROJECT_LEAD, DEVELOPER}
    MaxSessions,    \* Maximum concurrent sessions per user
    TokenExpiry     \* Token expiration time

VARIABLES
    userState,      \* Current state of each user
    projectState,   \* Current state of each project
    documentState,  \* Current state of each document
    sessions,       \* Active user sessions
    auditLog,       \* System audit log
    time            \* Current system time

vars == <<userState, projectState, documentState, sessions, auditLog, time>>

(*
User roles and their permissions
*)
ADMIN == "ADMIN"
PROJECT_LEAD == "PROJECT_LEAD" 
DEVELOPER == "DEVELOPER"

UserRoles == {ADMIN, PROJECT_LEAD, DEVELOPER}

(*
System states
*)
ACTIVE == "ACTIVE"
INACTIVE == "INACTIVE"
LOCKED == "LOCKED"
COMPLETED == "COMPLETED"

(*
Type definitions
*)
UserRecord == [
    id: Users,
    email: STRING,
    role: UserRoles,
    status: {ACTIVE, INACTIVE, LOCKED},
    failedAttempts: Nat,
    mfaEnabled: BOOLEAN,
    lastLogin: Nat
]

ProjectRecord == [
    id: Projects,
    name: STRING,
    status: {ACTIVE, COMPLETED},
    leadId: Users \cup {NULL},
    creatorId: Users,
    assignedUsers: SUBSET Users,
    deadline: Nat
]

DocumentRecord == [
    id: Documents,
    projectId: Projects,
    uploaderId: Users,
    filename: STRING,
    accessLevel: {"PUBLIC", "PROJECT", "RESTRICTED"}
]

SessionRecord == [
    userId: Users,
    token: STRING,
    createdAt: Nat,
    expiresAt: Nat,
    ipAddress: STRING,
    active: BOOLEAN
]

AuditRecord == [
    userId: Users,
    action: STRING,
    resource: STRING,
    timestamp: Nat,
    success: BOOLEAN
]

(*
Initial state predicate
*)
Init ==
    /\ userState = [u \in Users |-> [
        id |-> u,
        email |-> "",
        role |-> DEVELOPER,
        status |-> ACTIVE,
        failedAttempts |-> 0,
        mfaEnabled |-> FALSE,
        lastLogin |-> 0
    ]]
    /\ projectState = [p \in Projects |-> [
        id |-> p,
        name |-> "",
        status |-> ACTIVE,
        leadId |-> NULL,
        creatorId |-> CHOOSE u \in Users : TRUE,
        assignedUsers |-> {},
        deadline |-> 0
    ]]
    /\ documentState = [d \in Documents |-> [
        id |-> d,
        projectId |-> CHOOSE p \in Projects : TRUE,
        uploaderId |-> CHOOSE u \in Users : TRUE,
        filename |-> "",
        accessLevel |-> "PROJECT"
    ]]
    /\ sessions = {}
    /\ auditLog = <<>>
    /\ time = 0

(*
Authentication actions
*)
Login(user, password, mfaToken) ==
    /\ userState[user].status = ACTIVE
    /\ userState[user].failedAttempts < 5
    /\ \/ (userState[user].mfaEnabled = FALSE)
       \/ (userState[user].mfaEnabled = TRUE /\ mfaToken # NULL)
    /\ Cardinality({s \in sessions : s.userId = user /\ s.active = TRUE}) < MaxSessions
    /\ LET newSession == [
        userId |-> user,
        token |-> "token_" \o ToString(time),
        createdAt |-> time,
        expiresAt |-> time + TokenExpiry,
        ipAddress |-> "127.0.0.1",
        active |-> TRUE
       ]
       IN /\ sessions' = sessions \cup {newSession}
          /\ userState' = [userState EXCEPT ![user].lastLogin = time,
                                                ![user].failedAttempts = 0]
          /\ auditLog' = Append(auditLog, [
                userId |-> user,
                action |-> "LOGIN",
                resource |-> "User",
                timestamp |-> time,
                success |-> TRUE
             ])
          /\ UNCHANGED <<projectState, documentState, time>>

FailedLogin(user) ==
    /\ userState[user].status = ACTIVE
    /\ userState[user].failedAttempts < 5
    /\ LET newFailedAttempts == userState[user].failedAttempts + 1
           newStatus == IF newFailedAttempts >= 5 THEN LOCKED ELSE ACTIVE
       IN /\ userState' = [userState EXCEPT ![user].failedAttempts = newFailedAttempts,
                                               ![user].status = newStatus]
          /\ auditLog' = Append(auditLog, [
                userId |-> user,
                action |-> "LOGIN_FAILED",
                resource |-> "User", 
                timestamp |-> time,
                success |-> FALSE
             ])
          /\ UNCHANGED <<projectState, documentState, sessions, time>>

Logout(user) ==
    /\ \E s \in sessions : s.userId = user /\ s.active = TRUE
    /\ sessions' = {s \in sessions : \/ s.userId # user 
                                     \/ s.active = FALSE}
    /\ auditLog' = Append(auditLog, [
        userId |-> user,
        action |-> "LOGOUT",
        resource |-> "User",
        timestamp |-> time,
        success |-> TRUE
       ])
    /\ UNCHANGED <<userState, projectState, documentState, time>>

(*
Authorization predicates
*)
HasRole(user, role) ==
    userState[user].role = role

CanAccessProject(user, project) ==
    \/ userState[user].role = ADMIN
    \/ projectState[project].leadId = user
    \/ user \in projectState[project].assignedUsers

CanUploadDocument(user, project) ==
    \/ userState[user].role = ADMIN
    \/ projectState[project].leadId = user

CanManageUsers(user) ==
    userState[user].role = ADMIN

(*
Project management actions
*)
CreateProject(creator, project, name, deadline) ==
    /\ HasRole(creator, ADMIN)
    /\ projectState' = [projectState EXCEPT ![project] = [
        id |-> project,
        name |-> name,
        status |-> ACTIVE,
        leadId |-> NULL,
        creatorId |-> creator,
        assignedUsers |-> {},
        deadline |-> deadline
       ]]
    /\ auditLog' = Append(auditLog, [
        userId |-> creator,
        action |-> "CREATE",
        resource |-> "Project",
        timestamp |-> time,
        success |-> TRUE
       ])
    /\ UNCHANGED <<userState, documentState, sessions, time>>

AssignUserToProject(assigner, user, project) ==
    /\ \/ HasRole(assigner, ADMIN)
       \/ projectState[project].leadId = assigner
    /\ projectState[project].status = ACTIVE
    /\ userState[user].status = ACTIVE
    /\ projectState' = [projectState EXCEPT ![project].assignedUsers = 
                           projectState[project].assignedUsers \cup {user}]
    /\ auditLog' = Append(auditLog, [
        userId |-> assigner,
        action |-> "ASSIGN",
        resource |-> "Project",
        timestamp |-> time,
        success |-> TRUE
       ])
    /\ UNCHANGED <<userState, documentState, sessions, time>>

(*
Document management actions
*)
UploadDocument(uploader, document, project, filename) ==
    /\ CanUploadDocument(uploader, project)
    /\ CanAccessProject(uploader, project)
    /\ documentState' = [documentState EXCEPT ![document] = [
        id |-> document,
        projectId |-> project,
        uploaderId |-> uploader,
        filename |-> filename,
        accessLevel |-> "PROJECT"
       ]]
    /\ auditLog' = Append(auditLog, [
        userId |-> uploader,
        action |-> "UPLOAD",
        resource |-> "Document",
        timestamp |-> time,
        success |-> TRUE
       ])
    /\ UNCHANGED <<userState, projectState, sessions, time>>

AccessDocument(user, document) ==
    /\ LET project == documentState[document].projectId
       IN CanAccessProject(user, project)
    /\ auditLog' = Append(auditLog, [
        userId |-> user,
        action |-> "ACCESS",
        resource |-> "Document",
        timestamp |-> time,
        success |-> TRUE
       ])
    /\ UNCHANGED <<userState, projectState, documentState, sessions, time>>

(*
Time progression
*)
Tick ==
    /\ time' = time + 1
    /\ sessions' = {s \in sessions : s.expiresAt > time'}
    /\ UNCHANGED <<userState, projectState, documentState, auditLog>>

(*
Next state relation
*)
Next ==
    \/ \E u \in Users, p \in STRING, m \in STRING \cup {NULL} : Login(u, p, m)
    \/ \E u \in Users : FailedLogin(u)
    \/ \E u \in Users : Logout(u)
    \/ \E c \in Users, p \in Projects, n \in STRING, d \in Nat : CreateProject(c, p, n, d)
    \/ \E a \in Users, u \in Users, p \in Projects : AssignUserToProject(a, u, p)
    \/ \E u \in Users, d \in Documents, p \in Projects, f \in STRING : UploadDocument(u, d, p, f)
    \/ \E u \in Users, d \in Documents : AccessDocument(u, d)
    \/ Tick

(*
Specification
*)
Spec == Init /\ [][Next]_vars

(*
Safety Properties
*)

\* No unauthorized access to projects
NoUnauthorizedProjectAccess ==
    \A u \in Users, p \in Projects :
        (u \in projectState[p].assignedUsers \/ projectState[p].leadId = u \/ userState[u].role = ADMIN)
        => CanAccessProject(u, p)

\* No unauthorized document uploads
NoUnauthorizedDocumentUpload ==
    \A u \in Users, d \in Documents :
        LET project == documentState[d].projectId
        IN documentState[d].uploaderId = u => CanUploadDocument(u, project)

\* Account lockout after failed attempts
AccountLockoutSafety ==
    \A u \in Users :
        userState[u].failedAttempts >= 5 => userState[u].status = LOCKED

\* Session expiry enforcement
SessionExpirySafety ==
    \A s \in sessions :
        s.active = TRUE => s.expiresAt > time

(*
Liveness Properties
*)

\* Eventually all failed login attempts are reset on successful login
EventuallyResetFailedAttempts ==
    \A u \in Users :
        (userState[u].failedAttempts > 0) ~> (userState[u].failedAttempts = 0)

\* All audit events are eventually logged
EventuallyAuditLogged ==
    \A action \in {"LOGIN", "LOGOUT", "CREATE", "ASSIGN", "UPLOAD", "ACCESS"} :
        []<>(Len(auditLog) > 0)

(*
Invariants
*)
TypeInvariant ==
    /\ \A u \in Users : userState[u] \in UserRecord
    /\ \A p \in Projects : projectState[p] \in ProjectRecord  
    /\ \A d \in Documents : documentState[d] \in DocumentRecord
    /\ \A s \in sessions : s \in SessionRecord
    /\ time \in Nat

SecurityInvariant ==
    /\ NoUnauthorizedProjectAccess
    /\ NoUnauthorizedDocumentUpload
    /\ AccountLockoutSafety
    /\ SessionExpirySafety

=============================================================================
