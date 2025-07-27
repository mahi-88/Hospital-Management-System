import express from 'express';
import { body, validationResult } from 'express-validator';
import { PrismaClient, UserRole, ProjectStatus } from '@prisma/client';
import { authenticate, authorize, authorizeProjectAccess, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../utils/AppError';
import { auditLogger } from '../utils/auditLogger';

const router = express.Router();
const prisma = new PrismaClient();

/**
 * Get all projects (filtered by user role)
 * GET /api/projects
 */
router.get('/', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const userId = req.user!.id;
    const userRole = req.user!.role;

    let projects;

    if (userRole === UserRole.ADMIN) {
      // Admins can see all projects
      projects = await prisma.project.findMany({
        include: {
          creator: {
            select: { id: true, firstName: true, lastName: true, email: true }
          },
          lead: {
            select: { id: true, firstName: true, lastName: true, email: true }
          },
          assignments: {
            include: {
              user: {
                select: { id: true, firstName: true, lastName: true, email: true }
              }
            }
          },
          _count: {
            select: { documents: true }
          }
        },
        orderBy: { createdAt: 'desc' }
      });
    } else {
      // Project leads and developers see only assigned projects
      projects = await prisma.project.findMany({
        where: {
          OR: [
            { leadId: userId },
            { assignments: { some: { userId } } }
          ]
        },
        include: {
          creator: {
            select: { id: true, firstName: true, lastName: true, email: true }
          },
          lead: {
            select: { id: true, firstName: true, lastName: true, email: true }
          },
          assignments: {
            include: {
              user: {
                select: { id: true, firstName: true, lastName: true, email: true }
              }
            }
          },
          _count: {
            select: { documents: true }
          }
        },
        orderBy: { createdAt: 'desc' }
      });
    }

    res.json({
      success: true,
      data: { projects }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Get project by ID
 * GET /api/projects/:id
 */
router.get('/:id', authenticate, authorizeProjectAccess, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const project = await prisma.project.findUnique({
      where: { id },
      include: {
        creator: {
          select: { id: true, firstName: true, lastName: true, email: true }
        },
        lead: {
          select: { id: true, firstName: true, lastName: true, email: true }
        },
        assignments: {
          include: {
            user: {
              select: { id: true, firstName: true, lastName: true, email: true, role: true }
            }
          }
        },
        documents: {
          where: { isActive: true },
          include: {
            uploadedBy: {
              select: { id: true, firstName: true, lastName: true, email: true }
            }
          },
          orderBy: { createdAt: 'desc' }
        }
      }
    });

    if (!project) {
      return next(new AppError('Project not found', 404));
    }

    res.json({
      success: true,
      data: { project }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Create new project (Admin only)
 * POST /api/projects
 */
router.post('/', [
  authenticate,
  authorize(UserRole.ADMIN),
  body('name').trim().isLength({ min: 1, max: 255 }),
  body('description').optional().trim().isLength({ max: 1000 }),
  body('deadline').optional().isISO8601()
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { name, description, deadline } = req.body;
    const creatorId = req.user!.id;

    const project = await prisma.project.create({
      data: {
        name,
        description,
        deadline: deadline ? new Date(deadline) : null,
        creatorId
      },
      include: {
        creator: {
          select: { id: true, firstName: true, lastName: true, email: true }
        }
      }
    });

    // Log project creation
    await auditLogger.logAudit({
      action: 'CREATE',
      resource: 'Project',
      resourceId: project.id,
      userId: creatorId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: { name, description, deadline }
    });

    res.status(201).json({
      success: true,
      message: 'Project created successfully',
      data: { project }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Update project
 * PUT /api/projects/:id
 */
router.put('/:id', [
  authenticate,
  body('name').optional().trim().isLength({ min: 1, max: 255 }),
  body('description').optional().trim().isLength({ max: 1000 }),
  body('deadline').optional().isISO8601(),
  body('status').optional().isIn(['ACTIVE', 'COMPLETED', 'ON_HOLD', 'CANCELLED'])
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { id } = req.params;
    const { name, description, deadline, status } = req.body;
    const userId = req.user!.id;
    const userRole = req.user!.role;

    // Check if user can update this project
    const project = await prisma.project.findUnique({
      where: { id },
      select: { id: true, leadId: true, name: true, description: true, deadline: true, status: true }
    });

    if (!project) {
      return next(new AppError('Project not found', 404));
    }

    // Only admins and project leads can update projects
    if (userRole !== UserRole.ADMIN && project.leadId !== userId) {
      return next(new AppError('Insufficient permissions', 403));
    }

    const updatedProject = await prisma.project.update({
      where: { id },
      data: {
        ...(name && { name }),
        ...(description !== undefined && { description }),
        ...(deadline && { deadline: new Date(deadline) }),
        ...(status && { status: status as ProjectStatus })
      },
      include: {
        creator: {
          select: { id: true, firstName: true, lastName: true, email: true }
        },
        lead: {
          select: { id: true, firstName: true, lastName: true, email: true }
        }
      }
    });

    // Log project update
    await auditLogger.logAudit({
      action: 'UPDATE',
      resource: 'Project',
      resourceId: id,
      userId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      oldValues: { 
        name: project.name, 
        description: project.description, 
        deadline: project.deadline,
        status: project.status 
      },
      newValues: { name, description, deadline, status }
    });

    res.json({
      success: true,
      message: 'Project updated successfully',
      data: { project: updatedProject }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Assign user to project
 * POST /api/projects/:id/assign
 */
router.post('/:id/assign', [
  authenticate,
  body('userId').isUUID()
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { id: projectId } = req.params;
    const { userId: targetUserId } = req.body;
    const assignerId = req.user!.id;
    const assignerRole = req.user!.role;

    // Check if project exists and user has permission to assign
    const project = await prisma.project.findUnique({
      where: { id: projectId },
      select: { id: true, leadId: true, status: true }
    });

    if (!project) {
      return next(new AppError('Project not found', 404));
    }

    if (project.status !== ProjectStatus.ACTIVE) {
      return next(new AppError('Cannot assign users to inactive projects', 400));
    }

    // Only admins and project leads can assign users
    if (assignerRole !== UserRole.ADMIN && project.leadId !== assignerId) {
      return next(new AppError('Insufficient permissions', 403));
    }

    // Check if target user exists and is active
    const targetUser = await prisma.user.findUnique({
      where: { id: targetUserId },
      select: { id: true, isActive: true, firstName: true, lastName: true, email: true }
    });

    if (!targetUser || !targetUser.isActive) {
      return next(new AppError('User not found or inactive', 404));
    }

    // Check if user is already assigned
    const existingAssignment = await prisma.projectAssignment.findUnique({
      where: {
        userId_projectId: {
          userId: targetUserId,
          projectId
        }
      }
    });

    if (existingAssignment) {
      return next(new AppError('User is already assigned to this project', 409));
    }

    // Create assignment
    await prisma.projectAssignment.create({
      data: {
        userId: targetUserId,
        projectId,
        assignedBy: assignerId
      }
    });

    // Log assignment
    await auditLogger.logAudit({
      action: 'ASSIGN',
      resource: 'Project',
      resourceId: projectId,
      userId: assignerId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: { assignedUserId: targetUserId }
    });

    res.json({
      success: true,
      message: 'User assigned to project successfully',
      data: { 
        assignment: {
          userId: targetUserId,
          projectId,
          user: targetUser
        }
      }
    });
  } catch (error) {
    next(error);
  }
});

export default router;
