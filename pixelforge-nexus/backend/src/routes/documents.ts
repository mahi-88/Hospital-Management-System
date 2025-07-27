import express from 'express';
import multer from 'multer';
import path from 'path';
import fs from 'fs';
import { body, validationResult } from 'express-validator';
import { PrismaClient, UserRole, DocumentType } from '@prisma/client';
import { authenticate, authorizeProjectAccess, AuthenticatedRequest } from '../middleware/auth';
import { AppError } from '../utils/AppError';
import { auditLogger } from '../utils/auditLogger';
import { hashData } from '../utils/crypto';

const router = express.Router();
const prisma = new PrismaClient();

// Configure multer for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    const uploadDir = path.join(process.cwd(), 'uploads');
    if (!fs.existsSync(uploadDir)) {
      fs.mkdirSync(uploadDir, { recursive: true });
    }
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
    cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
  }
});

const fileFilter = (req: any, file: Express.Multer.File, cb: multer.FileFilterCallback) => {
  const allowedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
  ];

  if (allowedTypes.includes(file.mimetype)) {
    cb(null, true);
  } else {
    cb(new AppError('Invalid file type', 400));
  }
};

const upload = multer({
  storage,
  fileFilter,
  limits: {
    fileSize: 10 * 1024 * 1024 // 10MB limit
  }
});

/**
 * Upload document to project
 * POST /api/documents/upload
 */
router.post('/upload', [
  authenticate,
  upload.single('document'),
  body('projectId').isUUID(),
  body('type').optional().isIn(['DESIGN_DOC', 'MEETING_NOTES', 'SPECIFICATION', 'ASSET', 'OTHER']),
  body('description').optional().trim().isLength({ max: 500 })
], async (req: AuthenticatedRequest, res, next) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return next(new AppError('Validation failed', 400, errors.array()));
    }

    if (!req.file) {
      return next(new AppError('No file uploaded', 400));
    }

    const { projectId, type = 'OTHER', description } = req.body;
    const userId = req.user!.id;
    const userRole = req.user!.role;

    // Check if project exists and user has upload permission
    const project = await prisma.project.findUnique({
      where: { id: projectId },
      select: { id: true, leadId: true, status: true }
    });

    if (!project) {
      // Clean up uploaded file
      fs.unlinkSync(req.file.path);
      return next(new AppError('Project not found', 404));
    }

    // Only admins and project leads can upload documents
    if (userRole !== UserRole.ADMIN && project.leadId !== userId) {
      // Clean up uploaded file
      fs.unlinkSync(req.file.path);
      return next(new AppError('Insufficient permissions to upload documents', 403));
    }

    // Calculate file hash for integrity
    const fileBuffer = fs.readFileSync(req.file.path);
    const fileHash = hashData(fileBuffer.toString('base64'));

    // Create document record
    const document = await prisma.document.create({
      data: {
        filename: req.file.filename,
        originalName: req.file.originalname,
        mimeType: req.file.mimetype,
        size: req.file.size,
        type: type as DocumentType,
        description,
        filePath: req.file.path,
        fileHash,
        projectId,
        uploadedById: userId
      },
      include: {
        uploadedBy: {
          select: { id: true, firstName: true, lastName: true, email: true }
        },
        project: {
          select: { id: true, name: true }
        }
      }
    });

    // Log document upload
    await auditLogger.logAudit({
      action: 'UPLOAD',
      resource: 'Document',
      resourceId: document.id,
      userId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      newValues: {
        filename: document.originalName,
        projectId,
        type,
        size: document.size
      }
    });

    res.status(201).json({
      success: true,
      message: 'Document uploaded successfully',
      data: { document }
    });
  } catch (error) {
    // Clean up uploaded file on error
    if (req.file && fs.existsSync(req.file.path)) {
      fs.unlinkSync(req.file.path);
    }
    next(error);
  }
});

/**
 * Get documents for a project
 * GET /api/documents/project/:projectId
 */
router.get('/project/:projectId', authenticate, authorizeProjectAccess, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { projectId } = req.params;

    const documents = await prisma.document.findMany({
      where: {
        projectId,
        isActive: true
      },
      include: {
        uploadedBy: {
          select: { id: true, firstName: true, lastName: true, email: true }
        }
      },
      orderBy: { createdAt: 'desc' }
    });

    res.json({
      success: true,
      data: { documents }
    });
  } catch (error) {
    next(error);
  }
});

/**
 * Download document
 * GET /api/documents/:id/download
 */
router.get('/:id/download', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const userId = req.user!.id;
    const userRole = req.user!.role;

    // Get document with project information
    const document = await prisma.document.findUnique({
      where: { id, isActive: true },
      include: {
        project: {
          include: {
            assignments: {
              select: { userId: true }
            }
          }
        }
      }
    });

    if (!document) {
      return next(new AppError('Document not found', 404));
    }

    // Check if user has access to the project
    const hasAccess = userRole === UserRole.ADMIN ||
                     document.project.leadId === userId ||
                     document.project.assignments.some(a => a.userId === userId);

    if (!hasAccess) {
      return next(new AppError('Access denied', 403));
    }

    // Check if file exists
    if (!fs.existsSync(document.filePath)) {
      return next(new AppError('File not found on server', 404));
    }

    // Log document access
    await auditLogger.logAudit({
      action: 'DOWNLOAD',
      resource: 'Document',
      resourceId: document.id,
      userId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown'
    });

    // Set appropriate headers and send file
    res.setHeader('Content-Disposition', `attachment; filename="${document.originalName}"`);
    res.setHeader('Content-Type', document.mimeType);
    res.sendFile(path.resolve(document.filePath));
  } catch (error) {
    next(error);
  }
});

/**
 * Delete document
 * DELETE /api/documents/:id
 */
router.delete('/:id', authenticate, async (req: AuthenticatedRequest, res, next) => {
  try {
    const { id } = req.params;
    const userId = req.user!.id;
    const userRole = req.user!.role;

    // Get document with project information
    const document = await prisma.document.findUnique({
      where: { id, isActive: true },
      include: {
        project: {
          select: { id: true, leadId: true, name: true }
        }
      }
    });

    if (!document) {
      return next(new AppError('Document not found', 404));
    }

    // Only admins and project leads can delete documents
    if (userRole !== UserRole.ADMIN && document.project.leadId !== userId) {
      return next(new AppError('Insufficient permissions', 403));
    }

    // Soft delete the document
    await prisma.document.update({
      where: { id },
      data: { isActive: false }
    });

    // Log document deletion
    await auditLogger.logAudit({
      action: 'DELETE',
      resource: 'Document',
      resourceId: document.id,
      userId,
      ipAddress: req.ip || 'unknown',
      userAgent: req.get('User-Agent') || 'unknown',
      oldValues: {
        filename: document.originalName,
        projectId: document.projectId
      }
    });

    res.json({
      success: true,
      message: 'Document deleted successfully'
    });
  } catch (error) {
    next(error);
  }
});

export default router;
