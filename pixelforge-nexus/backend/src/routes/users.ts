import express from 'express';
import { body, validationResult } from 'express-validator';
import { PrismaClient, UserRole } from '@prisma/client';
import { authenticate, authorize, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../utils/AppError';
import { auditLogger } from '../utils/auditLogger';

const router = express.Router();
const prisma = new PrismaClient();

/**
 * Get all users (Admin only)
 * GET /api/users
 */
router.get('/', authenticate, authorize(UserRole.ADMIN), async (req: AuthenticatedRequest, res, next) => {
  try {
    const users = await prisma.user.findMany({
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        isActive: true,
        lastLogin: true,
        createdAt: true
      },
      orderBy: {
        createdAt: 'desc'
      }
    });

    res.json({
      success: true,
      data: { users }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Get current user profile
 * GET /api/users/profile
 */
router.get('/profile', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user!.id },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        mfaEnabled: true,
        lastLogin: true,
        createdAt: true
      }
    });

    if (!user) {
      return next(new AppError('User not found', 404));
    }

    res.json({
      success: true,
      data: { user }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Update user profile
 * PUT /api/users/profile
 */
router.put('/profile', [
  authenticate,
  body('firstName').optional().trim().isLength({ min: 1, max: 50 }),
  body('lastName').optional().trim().isLength({ min: 1, max: 50 }),
  body('email').optional().isEmail().normalizeEmail()
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { firstName, lastName, email } = req.body;
    const userId = req.user!.id;

    // Check if email is already taken by another user
    if (email) {
      const existingUser = await prisma.user.findFirst({
        where: {
          email,
          id: { not: userId }
        }
      });

      if (existingUser) {
        return next(new AppError('Email already in use', 409));
      }
    }

    const updatedUser = await prisma.user.update({
      where: { id: userId },
      data: {
        ...(firstName && { firstName }),
        ...(lastName && { lastName }),
        ...(email && { email })
      },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        mfaEnabled: true,
        lastLogin: true,
        createdAt: true
      }
    });

    // Log profile update
    await auditLogger.logAudit({
      action: 'UPDATE',
      resource: 'User',
      resourceId: userId,
      userId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: { firstName, lastName, email }
    });

    res.json({
      success: true,
      message: 'Profile updated successfully',
      data: { user: updatedUser }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Update user role (Admin only)
 * PUT /api/users/:id/role
 */
router.put('/:id/role', [
  authenticate,
  authorize(UserRole.ADMIN),
  body('role').isIn(['ADMIN', 'PROJECT_LEAD', 'DEVELOPER'])
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { id } = req.params;
    const { role } = req.body;

    const user = await prisma.user.findUnique({
      where: { id },
      select: { id: true, email: true, role: true }
    });

    if (!user) {
      return next(new AppError('User not found', 404));
    }

    const updatedUser = await prisma.user.update({
      where: { id },
      data: { role },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        isActive: true,
        lastLogin: true,
        createdAt: true
      }
    });

    // Log role change
    await auditLogger.logAudit({
      action: 'UPDATE',
      resource: 'User',
      resourceId: id,
      userId: req.user!.id,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      oldValues: { role: user.role },
      newValues: { role }
    });

    res.json({
      success: true,
      message: 'User role updated successfully',
      data: { user: updatedUser }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Deactivate user (Admin only)
 * PUT /api/users/:id/deactivate
 */
router.put('/:id/deactivate', authenticate, authorize(UserRole.ADMIN), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    // Prevent admin from deactivating themselves
    if (id === req.user!.id) {
      return next(new AppError('Cannot deactivate your own account', 400));
    }

    const user = await prisma.user.update({
      where: { id },
      data: { isActive: false },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        isActive: true
      }
    });

    // Deactivate all user sessions
    await prisma.userSession.updateMany({
      where: { userId: id },
      data: { isActive: false }
    });

    // Log user deactivation
    await auditLogger.logAudit({
      action: 'UPDATE',
      resource: 'User',
      resourceId: id,
      userId: req.user!.id,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: { isActive: false }
    });

    res.json({
      success: true,
      message: 'User deactivated successfully',
      data: { user }
    });
  } catch (error) {
    next(error);
  }
});

export default router;
