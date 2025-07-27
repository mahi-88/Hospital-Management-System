import { Request, Response } from 'express';
import { getRepository } from 'typeorm';
import { User } from '../models/User';
import { generateToken } from '../middleware/auth';
import { validateRequest, userRegistrationSchema, userLoginSchema } from '../middleware/validation';

export class AuthController {
  private userRepository = getRepository(User);

  // Register new user
  async register(req: Request, res: Response) {
    try {
      const { name, email, password } = req.body;

      // Check if user already exists
      const existingUser = await this.userRepository.findOne({ where: { email } });
      if (existingUser) {
        return res.status(409).json({
          error: 'User already exists',
          message: 'A user with this email already exists'
        });
      }

      // Create new user
      const user = new User();
      user.name = name;
      user.email = email;
      user.password = password;

      // Hash password
      await user.hashPassword();

      // Save user
      const savedUser = await this.userRepository.save(user);

      // Generate token
      const token = generateToken(savedUser);

      res.status(201).json({
        message: 'User registered successfully',
        user: savedUser.toJSON(),
        token,
        expiresIn: '24h'
      });
    } catch (error) {
      console.error('Registration error:', error);
      res.status(500).json({
        error: 'Registration failed',
        message: 'An error occurred during registration'
      });
    }
  }

  // Login user
  async login(req: Request, res: Response) {
    try {
      const { email, password } = req.body;

      // Find user by email
      const user = await this.userRepository.findOne({ where: { email } });
      if (!user) {
        return res.status(401).json({
          error: 'Invalid credentials',
          message: 'Email or password is incorrect'
        });
      }

      // Check if user is active
      if (user.status !== 'active') {
        return res.status(401).json({
          error: 'Account inactive',
          message: 'Your account has been deactivated'
        });
      }

      // Verify password
      const isPasswordValid = await user.comparePassword(password);
      if (!isPasswordValid) {
        return res.status(401).json({
          error: 'Invalid credentials',
          message: 'Email or password is incorrect'
        });
      }

      // Update last login
      user.lastLoginAt = new Date();
      await this.userRepository.save(user);

      // Generate token
      const token = generateToken(user);

      res.json({
        message: 'Login successful',
        user: user.toJSON(),
        token,
        expiresIn: '24h'
      });
    } catch (error) {
      console.error('Login error:', error);
      res.status(500).json({
        error: 'Login failed',
        message: 'An error occurred during login'
      });
    }
  }

  // Get current user profile
  async getProfile(req: any, res: Response) {
    try {
      const userId = req.user.id;
      
      const user = await this.userRepository.findOne({
        where: { id: userId },
        relations: ['jobs']
      });

      if (!user) {
        return res.status(404).json({
          error: 'User not found',
          message: 'User profile not found'
        });
      }

      res.json({
        user: user.getStats()
      });
    } catch (error) {
      console.error('Profile error:', error);
      res.status(500).json({
        error: 'Profile fetch failed',
        message: 'An error occurred while fetching profile'
      });
    }
  }

  // Update user profile
  async updateProfile(req: any, res: Response) {
    try {
      const userId = req.user.id;
      const { name, email } = req.body;

      const user = await this.userRepository.findOne({ where: { id: userId } });
      if (!user) {
        return res.status(404).json({
          error: 'User not found',
          message: 'User profile not found'
        });
      }

      // Check if email is already taken by another user
      if (email && email !== user.email) {
        const existingUser = await this.userRepository.findOne({ where: { email } });
        if (existingUser) {
          return res.status(409).json({
            error: 'Email already taken',
            message: 'This email is already registered to another account'
          });
        }
      }

      // Update user fields
      if (name) user.name = name;
      if (email) user.email = email;

      const updatedUser = await this.userRepository.save(user);

      res.json({
        message: 'Profile updated successfully',
        user: updatedUser.toJSON()
      });
    } catch (error) {
      console.error('Profile update error:', error);
      res.status(500).json({
        error: 'Profile update failed',
        message: 'An error occurred while updating profile'
      });
    }
  }

  // Change password
  async changePassword(req: any, res: Response) {
    try {
      const userId = req.user.id;
      const { currentPassword, newPassword } = req.body;

      const user = await this.userRepository.findOne({ where: { id: userId } });
      if (!user) {
        return res.status(404).json({
          error: 'User not found',
          message: 'User profile not found'
        });
      }

      // Verify current password
      const isCurrentPasswordValid = await user.comparePassword(currentPassword);
      if (!isCurrentPasswordValid) {
        return res.status(401).json({
          error: 'Invalid current password',
          message: 'The current password is incorrect'
        });
      }

      // Update password
      user.password = newPassword;
      await user.hashPassword();
      await this.userRepository.save(user);

      res.json({
        message: 'Password changed successfully'
      });
    } catch (error) {
      console.error('Password change error:', error);
      res.status(500).json({
        error: 'Password change failed',
        message: 'An error occurred while changing password'
      });
    }
  }

  // Logout (client-side token removal)
  async logout(req: Request, res: Response) {
    res.json({
      message: 'Logout successful',
      note: 'Please remove the token from client storage'
    });
  }
}
