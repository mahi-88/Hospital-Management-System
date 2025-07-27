import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

interface AuditLogData {
  action: string;
  resource: string;
  resourceId?: string;
  userId?: string;
  ipAddress: string;
  userAgent: string;
  oldValues?: any;
  newValues?: any;
  success?: boolean;
  errorMessage?: string;
}

interface SecurityEventData {
  eventType: string;
  severity: string;
  description: string;
  ipAddress: string;
  userAgent?: string;
  userId?: string;
  metadata?: any;
}

class AuditLogger {
  async logAudit(data: AuditLogData): Promise<void> {
    try {
      await prisma.auditLog.create({
        data: {
          action: data.action as any,
          resource: data.resource,
          resourceId: data.resourceId,
          userId: data.userId,
          ipAddress: data.ipAddress,
          userAgent: data.userAgent,
          oldValues: data.oldValues,
          newValues: data.newValues,
          success: data.success ?? true,
          errorMessage: data.errorMessage
        }
      });
    } catch (error) {
      console.error('Failed to log audit event:', error);
    }
  }

  async logSecurityEvent(data: SecurityEventData): Promise<void> {
    try {
      await prisma.securityEvent.create({
        data: {
          eventType: data.eventType,
          severity: data.severity,
          description: data.description,
          ipAddress: data.ipAddress,
          userAgent: data.userAgent,
          userId: data.userId,
          metadata: data.metadata
        }
      });
    } catch (error) {
      console.error('Failed to log security event:', error);
    }
  }
}

export const auditLogger = new AuditLogger();
