import { Router } from 'express';
import { AuthController } from '../controllers/AuthController';
import { validateRequest, userRegistrationSchema, userLoginSchema } from '../middleware/validation';
import { authenticateToken } from '../middleware/auth';
import rateLimit from 'express-rate-limit';

const router = Router();
const authController = new AuthController();

// Rate limiting for auth endpoints
const authLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 5, // limit each IP to 5 requests per windowMs
  message: {
    error: 'Too many authentication attempts',
    message: 'Please try again later'
  }
});

const generalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
  message: {
    error: 'Too many requests',
    message: 'Please try again later'
  }
});

// Public routes
router.post('/register', 
  authLimiter,
  validateRequest(userRegistrationSchema),
  authController.register.bind(authController)
);

router.post('/login',
  authLimiter,
  validateRequest(userLoginSchema),
  authController.login.bind(authController)
);

// Protected routes
router.get('/profile',
  generalLimiter,
  authenticateToken,
  authController.getProfile.bind(authController)
);

router.put('/profile',
  generalLimiter,
  authenticateToken,
  authController.updateProfile.bind(authController)
);

router.post('/change-password',
  authLimiter,
  authenticateToken,
  authController.changePassword.bind(authController)
);

router.post('/logout',
  generalLimiter,
  authenticateToken,
  authController.logout.bind(authController)
);

export default router;
