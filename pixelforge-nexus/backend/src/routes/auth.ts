import express from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import speakeasy from 'speakeasy';
import QRCode from 'qrcode';
import { body, validationResult } from 'express-validator';
import { PrismaClient } from '@prisma/client';
import { AppError } from '../utils/AppError';
import { auditLogger } from '../utils/auditLogger';
import { authenticate, AuthenticatedRequest } from '../middleware/auth';
import { generateSecureToken } from '../utils/crypto';

const router = express.Router();
const prisma = new PrismaClient();

/**
 * User Registration (Admin Only)
 * POST /api/auth/register
 */
router.post('/register', [
  body('email').isEmail().normalizeEmail(),
  body('password').isLength({ min: 8 }).matches(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/),
  body('firstName').trim().isLength({ min: 1, max: 50 }),
  body('lastName').trim().isLength({ min: 1, max: 50 }),
  body('role').isIn(['ADMIN', 'PROJECT_LEAD', 'DEVELOPER'])
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { email, password, firstName, lastName, role } = req.body;

    // Check if user already exists
    const existingUser = await prisma.user.findUnique({
      where: { email }
    });

    if (existingUser) {
      return next(new AppError('User already exists', 409));
    }

    // Hash password
    const saltRounds = 12;
    const passwordHash = await bcrypt.hash(password, saltRounds);

    // Create user
    const user = await prisma.user.create({
      data: {
        email,
        passwordHash,
        firstName,
        lastName,
        role,
        emailVerificationToken: generateSecureToken()
      },
      select: {
        id: true,
        email: true,
        firstName: true,
        lastName: true,
        role: true,
        createdAt: true
      }
    });

    // Log user creation
    await auditLogger.logAudit({
      action: 'CREATE',
      resource: 'User',
      resourceId: user.id,
      userId: req.user?.id,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: { email, firstName, lastName, role }
    });

    res.status(201).json({
      success: true,
      message: 'User created successfully',
      data: { user }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * User Login
 * POST /api/auth/login
 */
router.post('/login', [
  body('email').isEmail().normalizeEmail(),
  body('password').notEmpty(),
  body('mfaToken').optional().isLength({ min: 6, max: 6 })
], async (req, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const { email, password, mfaToken } = req.body;
    const ipAddress = req.ip || 'unknown';
    const userAgent = req.get('User-Agent') || 'unknown';

    // Find user
    const user = await prisma.user.findUnique({
      where: { email },
      select: {
        id: true,
        email: true,
        passwordHash: true,
        firstName: true,
        lastName: true,
        role: true,
        isActive: true,
        failedLoginAttempts: true,
        lockedUntil: true,
        mfaEnabled: true,
        mfaSecret: true
      }
    });

    if (!user) {
      await auditLogger.logSecurityEvent({
        eventType: 'LOGIN_FAILED',
        severity: 'WARNING',
        description: 'Login attempt with non-existent email',
        ipAddress,
        userAgent,
        metadata: { email }
      });
      return next(new AppError('Invalid credentials', 401));
    }

    // Check if account is locked
    if (user.lockedUntil && user.lockedUntil > new Date()) {
      await auditLogger.logSecurityEvent({
        eventType: 'LOGIN_BLOCKED',
        severity: 'HIGH',
        description: 'Login attempt on locked account',
        ipAddress,
        userAgent,
        userId: user.id
      });
      return next(new AppError('Account is temporarily locked', 401));
    }

    // Check if account is active
    if (!user.isActive) {
      return next(new AppError('Account is deactivated', 401));
    }

    // Verify password
    const isPasswordValid = await bcrypt.compare(password, user.passwordHash);
    
    if (!isPasswordValid) {
      // Increment failed login attempts
      const failedAttempts = user.failedLoginAttempts + 1;
      const lockUntil = failedAttempts >= 5 ? new Date(Date.now() + 15 * 60 * 1000) : null;

      await prisma.user.update({
        where: { id: user.id },
        data: {
          failedLoginAttempts: failedAttempts,
          lockedUntil: lockUntil
        }
      });

      await auditLogger.logSecurityEvent({
        eventType: 'LOGIN_FAILED',
        severity: 'WARNING',
        description: 'Login attempt with invalid password',
        ipAddress,
        userAgent,
        userId: user.id,
        metadata: { failedAttempts }
      });

      return next(new AppError('Invalid credentials', 401));
    }

    // Verify MFA if enabled
    if (user.mfaEnabled) {
      if (!mfaToken) {
        return next(new AppError('MFA token required', 401));
      }

      const isValidMFA = speakeasy.totp.verify({
        secret: user.mfaSecret!,
        encoding: 'base32',
        token: mfaToken,
        window: 2
      });

      if (!isValidMFA) {
        await auditLogger.logSecurityEvent({
          eventType: 'MFA_FAILED',
          severity: 'HIGH',
          description: 'Invalid MFA token provided',
          ipAddress,
          userAgent,
          userId: user.id
        });
        return next(new AppError('Invalid MFA token', 401));
      }
    }

    // Reset failed login attempts
    await prisma.user.update({
      where: { id: user.id },
      data: {
        failedLoginAttempts: 0,
        lockedUntil: null,
        lastLogin: new Date()
      }
    });

    // Generate JWT tokens
    const accessToken = jwt.sign(
      { userId: user.id, email: user.email, role: user.role },
      process.env.JWT_SECRET!,
      { expiresIn: '15m' }
    );

    const refreshToken = jwt.sign(
      { userId: user.id },
      process.env.JWT_REFRESH_SECRET!,
      { expiresIn: '7d' }
    );

    // Create session
    const session = await prisma.userSession.create({
      data: {
        userId: user.id,
        sessionToken: accessToken,
        refreshToken,
        ipAddress,
        userAgent,
        expiresAt: new Date(Date.now() + 15 * 60 * 1000) // 15 minutes
      }
    });

    // Log successful login
    await auditLogger.logAudit({
      action: 'LOGIN',
      resource: 'User',
      resourceId: user.id,
      userId: user.id,
      ipAddress,
      userAgent
    });

    res.json({
      success: true,
      message: 'Login successful',
      data: {
        user: {
          id: user.id,
          email: user.email,
          firstName: user.firstName,
          lastName: user.lastName,
          role: user.role
        },
        accessToken,
        refreshToken,
        expiresIn: 900 // 15 minutes in seconds
      }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Refresh Token
 * POST /api/auth/refresh
 */
router.post('/refresh', [
  body('refreshToken').notEmpty()
], async (req, res, next) => {
  try {
    const { refreshToken } = req.body;

    // Verify refresh token
    const decoded = jwt.verify(refreshToken, process.env.JWT_REFRESH_SECRET!) as any;

    // Find active session
    const session = await prisma.userSession.findFirst({
      where: {
        refreshToken,
        isActive: true,
        userId: decoded.userId
      },
      include: {
        user: {
          select: {
            id: true,
            email: true,
            role: true,
            isActive: true
          }
        }
      }
    });

    if (!session || !session.user.isActive) {
      return next(new AppError('Invalid refresh token', 401));
    }

    // Generate new access token
    const newAccessToken = jwt.sign(
      { userId: session.user.id, email: session.user.email, role: session.user.role },
      process.env.JWT_SECRET!,
      { expiresIn: '15m' }
    );

    // Update session
    await prisma.userSession.update({
      where: { id: session.id },
      data: {
        sessionToken: newAccessToken,
        expiresAt: new Date(Date.now() + 15 * 60 * 1000)
      }
    });

    res.json({
      success: true,
      data: {
        accessToken: newAccessToken,
        expiresIn: 900
      }
    });
  } catch (error) {
    next(new AppError('Invalid refresh token', 401));
  }
});

/**
 * Logout
 * POST /api/auth/logout
 */
router.post('/logout', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader?.substring(7);

    if (token) {
      // Deactivate session
      await prisma.userSession.updateMany({
        where: {
          sessionToken: token,
          userId: req.user!.id
        },
        data: {
          isActive: false
        }
      });
    }

    // Log logout
    await auditLogger.logAudit({
      action: 'LOGOUT',
      resource: 'User',
      resourceId: req.user!.id,
      userId: req.user!.id,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown'
    });

    res.json({
      success: true,
      message: 'Logged out successfully'
    });
  } catch (error) {
    next(error);
  }
});

export default router;
