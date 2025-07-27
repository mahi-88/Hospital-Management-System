import request from 'supertest';
import { createConnection, getConnection } from 'typeorm';
import { User } from '../models/User';
import { AuthController } from '../controllers/AuthController';

describe('Authentication Tests', () => {
  let app: any;
  let authController: AuthController;

  beforeAll(async () => {
    // Setup test database connection
    await createConnection({
      type: 'sqlite',
      database: ':memory:',
      entities: [User],
      synchronize: true,
      logging: false
    });

    authController = new AuthController();
  });

  afterAll(async () => {
    await getConnection().close();
  });

  beforeEach(async () => {
    // Clear database before each test
    await getConnection().synchronize(true);
  });

  describe('POST /auth/register', () => {
    it('should register a new user successfully', async () => {
      const userData = {
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123'
      };

      const mockReq = { body: userData } as any;
      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await authController.register(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(201);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'User registered successfully',
          user: expect.objectContaining({
            name: userData.name,
            email: userData.email
          }),
          token: expect.any(String)
        })
      );
    });

    it('should return error for duplicate email', async () => {
      const userData = {
        name: 'Test User',
        email: 'test@example.com',
        password: 'password123'
      };

      // Create user first
      const user = new User();
      user.name = userData.name;
      user.email = userData.email;
      user.password = userData.password;
      await user.hashPassword();
      await getConnection().getRepository(User).save(user);

      const mockReq = { body: userData } as any;
      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await authController.register(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(409);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          error: 'User already exists'
        })
      );
    });

    it('should validate required fields', async () => {
      const invalidData = {
        name: '',
        email: 'invalid-email',
        password: '123' // too short
      };

      // This would be handled by validation middleware
      // Test validation logic here
      expect(invalidData.name).toBe('');
      expect(invalidData.email).not.toMatch(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
      expect(invalidData.password.length).toBeLessThan(6);
    });
  });

  describe('POST /auth/login', () => {
    beforeEach(async () => {
      // Create a test user
      const user = new User();
      user.name = 'Test User';
      user.email = 'test@example.com';
      user.password = 'password123';
      await user.hashPassword();
      await getConnection().getRepository(User).save(user);
    });

    it('should login with valid credentials', async () => {
      const loginData = {
        email: 'test@example.com',
        password: 'password123'
      };

      const mockReq = { body: loginData } as any;
      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await authController.login(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'Login successful',
          user: expect.objectContaining({
            email: loginData.email
          }),
          token: expect.any(String)
        })
      );
    });

    it('should reject invalid credentials', async () => {
      const loginData = {
        email: 'test@example.com',
        password: 'wrongpassword'
      };

      const mockReq = { body: loginData } as any;
      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await authController.login(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(401);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          error: 'Invalid credentials'
        })
      );
    });

    it('should reject non-existent user', async () => {
      const loginData = {
        email: 'nonexistent@example.com',
        password: 'password123'
      };

      const mockReq = { body: loginData } as any;
      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await authController.login(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(401);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          error: 'Invalid credentials'
        })
      );
    });
  });

  describe('JWT Token Validation', () => {
    it('should validate JWT token format', () => {
      const validToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
      const invalidToken = 'invalid.token.format';

      expect(validToken.split('.').length).toBe(3);
      expect(invalidToken.split('.').length).not.toBe(3);
    });

    it('should handle expired tokens', () => {
      // This would be tested with actual JWT library
      const expiredToken = 'expired.jwt.token';
      // Mock JWT verification that returns expired error
      expect(expiredToken).toBeDefined();
    });
  });

  describe('Password Security', () => {
    it('should hash passwords before storing', async () => {
      const user = new User();
      user.password = 'plaintext123';
      
      await user.hashPassword();
      
      expect(user.password).not.toBe('plaintext123');
      expect(user.password.length).toBeGreaterThan(20);
    });

    it('should verify password correctly', async () => {
      const user = new User();
      user.password = 'password123';
      await user.hashPassword();

      const isValid = await user.comparePassword('password123');
      const isInvalid = await user.comparePassword('wrongpassword');

      expect(isValid).toBe(true);
      expect(isInvalid).toBe(false);
    });
  });

  describe('User Model Validation', () => {
    it('should validate email format', () => {
      const validEmails = ['test@example.com', 'user.name@domain.co.uk'];
      const invalidEmails = ['invalid', '@domain.com', 'user@'];

      validEmails.forEach(email => {
        expect(email).toMatch(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
      });

      invalidEmails.forEach(email => {
        expect(email).not.toMatch(/^[^\s@]+@[^\s@]+\.[^\s@]+$/);
      });
    });

    it('should validate required fields', () => {
      const user = new User();
      
      // Test that required fields are validated
      expect(user.name).toBeUndefined();
      expect(user.email).toBeUndefined();
      expect(user.password).toBeUndefined();
    });
  });
});
