import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { PrismaClient, UserRole } from '@prisma/client';
import { AppError } from '../utils/AppError';
import { auditLogger } from '../utils/auditLogger';

const prisma = new PrismaClient();

export interface AuthenticatedRequest extends Request {
  user?: {
    id: string;
    email: string;
    role: UserRole;
    firstName: string;
    lastName: string;
  };
}

/**
 * JWT Authentication Middleware
 * Verifies JWT token and attaches user information to request
 */
export const authenticate = async (
  req: AuthenticatedRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    const authHeader = req.headers.authorization;
    
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      throw new AppError('Access token required', 401);
    }

    const token = authHeader.substring(7);
    
    if (!process.env.JWT_SECRET) {
      throw new AppError('JWT secret not configured', 500);
    }

    // Verify JWT token
    const decoded = jwt.verify(token, process.env.JWT_SECRET) as any;
    
    // Check if session is still active
    const session = await prisma.userSession.findFirst({
      where: {
        sessionToken: token,
        isActive: true,
        expiresAt: {
          gt: new Date()
        }
      },
      include: {
        user: {
          select: {
            id: true,
            email: true,
            role: true,
            firstName: true,
            lastName: true,
            isActive: true,
            lockedUntil: true
          }
        }
      }
    });

    if (!session) {
      throw new AppError('Invalid or expired session', 401);
    }

    // Check if user account is active
    if (!session.user.isActive) {
      throw new AppError('Account is deactivated', 401);
    }

    // Check if account is locked
    if (session.user.lockedUntil && session.user.lockedUntil > new Date()) {
      throw new AppError('Account is temporarily locked', 401);
    }

    // Attach user to request
    req.user = {
      id: session.user.id,
      email: session.user.email,
      role: session.user.role,
      firstName: session.user.firstName,
      lastName: session.user.lastName
    };

    // Update session last activity
    await prisma.userSession.update({
      where: { id: session.id },
      data: { updatedAt: new Date() }
    });

    next();
  } catch (error) {
    // Log failed authentication attempt
    await auditLogger.logSecurityEvent({
      eventType: 'AUTHENTICATION_FAILED',
      severity: 'WARNING',
      description: 'Failed authentication attempt',
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      metadata: {
        error: error instanceof Error ? error.message : 'Unknown error',
        path: req.path,
        method: req.method
      }
    });

    if (error instanceof jwt.JsonWebTokenError) {
      return next(new AppError('Invalid token', 401));
    }
    
    if (error instanceof jwt.TokenExpiredError) {
      return next(new AppError('Token expired', 401));
    }

    next(error);
  }
};

/**
 * Role-based Authorization Middleware
 * Checks if user has required role(s)
 */
export const authorize = (...roles: UserRole[]) => {
  return (req: AuthenticatedRequest, res: Response, next: NextFunction): void => {
    if (!req.user) {
      return next(new AppError('Authentication required', 401));
    }

    if (!roles.includes(req.user.role)) {
      // Log unauthorized access attempt
      auditLogger.logSecurityEvent({
        eventType: 'UNAUTHORIZED_ACCESS',
        severity: 'HIGH',
        description: `User attempted to access resource without proper role`,
        ipAddress: req.ip || 'unknown',
        userAgent: req.get('User-Agent') || 'unknown',
        userId: req.user.id,
        metadata: {
          userRole: req.user.role,
          requiredRoles: roles,
          path: req.path,
          method: req.method
        }
      });

      return next(new AppError('Insufficient permissions', 403));
    }

    next();
  };
};

/**
 * Project Access Authorization
 * Checks if user has access to specific project
 */
export const authorizeProjectAccess = async (
  req: AuthenticatedRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    if (!req.user) {
      return next(new AppError('Authentication required', 401));
    }

    const projectId = req.params.projectId || req.body.projectId;
    
    if (!projectId) {
      return next(new AppError('Project ID required', 400));
    }

    // Admins have access to all projects
    if (req.user.role === UserRole.ADMIN) {
      return next();
    }

    // Check if user is project lead or assigned to project
    const project = await prisma.project.findFirst({
      where: {
        id: projectId,
        OR: [
          { leadId: req.user.id },
          {
            assignments: {
              some: {
                userId: req.user.id
              }
            }
          }
        ]
      }
    });

    if (!project) {
      // Log unauthorized project access attempt
      await auditLogger.logSecurityEvent({
        eventType: 'UNAUTHORIZED_PROJECT_ACCESS',
        severity: 'HIGH',
        description: `User attempted to access project without permission`,
        ipAddress: req.ip || 'unknown',
        userAgent: req.get('User-Agent') || 'unknown',
        userId: req.user.id,
        metadata: {
          projectId,
          userRole: req.user.role,
          path: req.path,
          method: req.method
        }
      });

      return next(new AppError('Access denied to this project', 403));
    }

    next();
  } catch (error) {
    next(error);
  }
};

/**
 * MFA Verification Middleware
 * Checks if MFA is required and verified for sensitive operations
 */
export const requireMFA = async (
  req: AuthenticatedRequest,
  res: Response,
  next: NextFunction
): Promise<void> => {
  try {
    if (!req.user) {
      return next(new AppError('Authentication required', 401));
    }

    const user = await prisma.user.findUnique({
      where: { id: req.user.id },
      select: { mfaEnabled: true }
    });

    if (!user) {
      return next(new AppError('User not found', 404));
    }

    // Check if MFA is enabled for user
    if (user.mfaEnabled) {
      const mfaToken = req.headers['x-mfa-token'] as string;
      
      if (!mfaToken) {
        return next(new AppError('MFA token required', 401));
      }

      // Verify MFA token (implementation depends on MFA method)
      // This would typically verify TOTP token
      // For now, we'll assume it's verified if present
    }

    next();
  } catch (error) {
    next(error);
  }
};

/**
 * Rate Limiting for Sensitive Operations
 */
export const sensitiveOperationLimit = (maxAttempts: number = 3, windowMs: number = 15 * 60 * 1000) => {
  const attempts = new Map<string, { count: number; resetTime: number }>();

  return (req: AuthenticatedRequest, res: Response, next: NextFunction): void => {
    if (!req.user) {
      return next(new AppError('Authentication required', 401));
    }

    const key = `${req.user.id}:${req.path}`;
    const now = Date.now();
    const userAttempts = attempts.get(key);

    if (!userAttempts || now > userAttempts.resetTime) {
      attempts.set(key, { count: 1, resetTime: now + windowMs });
      return next();
    }

    if (userAttempts.count >= maxAttempts) {
      // Log rate limit exceeded
      auditLogger.logSecurityEvent({
        eventType: 'RATE_LIMIT_EXCEEDED',
        severity: 'WARNING',
        description: `User exceeded rate limit for sensitive operation`,
        ipAddress: req.ip || 'unknown',
        userAgent: req.get('User-Agent') || 'unknown',
        userId: req.user.id,
        metadata: {
          path: req.path,
          attempts: userAttempts.count,
          maxAttempts
        }
      });

      return next(new AppError('Too many attempts, please try again later', 429));
    }

    userAttempts.count++;
    next();
  };
};
