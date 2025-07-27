import { Router } from 'express';
import { JobController } from '../controllers/JobControllerEnhanced';
import { authenticateToken } from '../middleware/auth';
import { validateRequest, validateQuery, jobCreateSchema, jobUpdateSchema, jobQuerySchema } from '../middleware/validation';
import rateLimit from 'express-rate-limit';

const router = Router();
const jobController = new JobController();

// Rate limiting
const generalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
  message: {
    error: 'Too many requests',
    message: 'Please try again later'
  }
});

// All job routes require authentication
router.use(authenticateToken);
router.use(generalLimiter);

// GET /api/jobs - Get all jobs with filtering and search
router.get('/',
  validateQuery(jobQuerySchema),
  jobController.getAllJobs.bind(jobController)
);

// GET /api/jobs/statistics - Get dashboard statistics
router.get('/statistics',
  jobController.getDashboardStats.bind(jobController)
);

// GET /api/jobs/:id - Get job by ID
router.get('/:id',
  jobController.getJobById.bind(jobController)
);

// POST /api/jobs - Create new job
router.post('/',
  validateRequest(jobCreateSchema),
  jobController.createJob.bind(jobController)
);

// PUT /api/jobs/:id - Update job
router.put('/:id',
  validateRequest(jobUpdateSchema),
  jobController.updateJob.bind(jobController)
);

// DELETE /api/jobs/:id - Delete job
router.delete('/:id',
  jobController.deleteJob.bind(jobController)
);

export default router;
