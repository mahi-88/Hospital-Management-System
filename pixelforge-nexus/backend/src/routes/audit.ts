import express from 'express';
import { query, validationResult } from 'express-validator';
import { PrismaClient, UserRole } from '@prisma/client';
import { authenticate, authorize, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../utils/AppError';

const router = express.Router();
const prisma = new PrismaClient();

/**
 * Get audit logs (Admin only)
 * GET /api/audit/logs
 */
router.get('/logs', [
  authenticate,
  authorize(UserRole.ADMIN),
  query('page').optional().isInt({ min: 1 }),
  query('limit').optional().isInt({ min: 1, max: 100 }),
  query('action').optional().isString(),
  query('resource').optional().isString(),
  query('userId').optional().isUUID(),
  query('startDate').optional().isISO8601(),
  query('endDate').optional().isISO8601()
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const page = parseInt(req.query.page as string) || 1;
    const limit = parseInt(req.query.limit as string) || 50;
    const { action, resource, userId, startDate, endDate } = req.query;

    const skip = (page - 1) * limit;

    // Build where clause
    const where: any = {};
    
    if (action) where.action = action;
    if (resource) where.resource = resource;
    if (userId) where.userId = userId;
    
    if (startDate || endDate) {
      where.createdAt = {};
      if (startDate) where.createdAt.gte = new Date(startDate as string);
      if (endDate) where.createdAt.lte = new Date(endDate as string);
    }

    // Get audit logs with pagination
    const [auditLogs, totalCount] = await Promise.all([
      prisma.auditLog.findMany({
        where,
        include: {
          user: {
            select: { id: true, firstName: true, lastName: true, email: true }
          },
          project: {
            select: { id: true, name: true }
          }
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit
      }),
      prisma.auditLog.count({ where })
    ]);

    const totalPages = Math.ceil(totalCount / limit);

    res.json({
      success: true,
      data: {
        auditLogs,
        pagination: {
          page,
          limit,
          totalCount,
          totalPages,
          hasNext: page < totalPages,
          hasPrev: page > 1
        }
      }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Get security events (Admin only)
 * GET /api/audit/security-events
 */
router.get('/security-events', [
  authenticate,
  authorize(UserRole.ADMIN),
  query('page').optional().isInt({ min: 1 }),
  query('limit').optional().isInt({ min: 1, max: 100 }),
  query('severity').optional().isIn(['INFO', 'WARNING', 'HIGH', 'CRITICAL']),
  query('eventType').optional().isString(),
  query('resolved').optional().isBoolean()
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    const page = parseInt(req.query.page as string) || 1;
    const limit = parseInt(req.query.limit as string) || 50;
    const { severity, eventType, resolved } = req.query;

    const skip = (page - 1) * limit;

    // Build where clause
    const where: any = {};
    
    if (severity) where.severity = severity;
    if (eventType) where.eventType = eventType;
    if (resolved !== undefined) where.resolved = resolved === 'true';

    // Get security events with pagination
    const [securityEvents, totalCount] = await Promise.all([
      prisma.securityEvent.findMany({
        where,
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit
      }),
      prisma.securityEvent.count({ where })
    ]);

    const totalPages = Math.ceil(totalCount / limit);

    res.json({
      success: true,
      data: {
        securityEvents,
        pagination: {
          page,
          limit,
          totalCount,
          totalPages,
          hasNext: page < totalPages,
          hasPrev: page > 1
        }
      }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Get audit statistics (Admin only)
 * GET /api/audit/statistics
 */
router.get('/statistics', authenticate, authorize(UserRole.ADMIN), async (req: AuthenticatedRequest, res, next) => {
  try {
    const now = new Date();
    const last24Hours = new Date(now.getTime() - 24 * 60 * 60 * 1000);
    const last7Days = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    const last30Days = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);

    // Get various statistics
    const [
      totalAuditLogs,
      auditLogsLast24h,
      auditLogsLast7d,
      auditLogsLast30d,
      totalSecurityEvents,
      securityEventsLast24h,
      unresolvedSecurityEvents,
      actionBreakdown,
      severityBreakdown
    ] = await Promise.all([
      prisma.auditLog.count(),
      prisma.auditLog.count({ where: { createdAt: { gte: last24Hours } } }),
      prisma.auditLog.count({ where: { createdAt: { gte: last7Days } } }),
      prisma.auditLog.count({ where: { createdAt: { gte: last30Days } } }),
      prisma.securityEvent.count(),
      prisma.securityEvent.count({ where: { createdAt: { gte: last24Hours } } }),
      prisma.securityEvent.count({ where: { resolved: false } }),
      prisma.auditLog.groupBy({
        by: ['action'],
        _count: { action: true },
        where: { createdAt: { gte: last7Days } }
      }),
      prisma.securityEvent.groupBy({
        by: ['severity'],
        _count: { severity: true },
        where: { createdAt: { gte: last7Days } }
      })
    ]);

    res.json({
      success: true,
      data: {
        auditLogs: {
          total: totalAuditLogs,
          last24Hours: auditLogsLast24h,
          last7Days: auditLogsLast7d,
          last30Days: auditLogsLast30d
        },
        securityEvents: {
          total: totalSecurityEvents,
          last24Hours: securityEventsLast24h,
          unresolved: unresolvedSecurityEvents
        },
        breakdowns: {
          actionBreakdown: actionBreakdown.map(item => ({
            action: item.action,
            count: item._count.action
          })),
          severityBreakdown: severityBreakdown.map(item => ({
            severity: item.severity,
            count: item._count.severity
          }))
        }
      }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Resolve security event (Admin only)
 * PUT /api/audit/security-events/:id/resolve
 */
router.put('/security-events/:id/resolve', authenticate, authorize(UserRole.ADMIN), async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;

    const securityEvent = await prisma.securityEvent.update({
      where: { id },
      data: {
        resolved: true,
        resolvedAt: new Date()
      }
    });

    res.json({
      success: true,
      message: 'Security event resolved successfully',
      data: { securityEvent }
    });
  } catch (error) {
    next(error);
  }
});

export default router;
