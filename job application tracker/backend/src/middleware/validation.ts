import { Request, Response, NextFunction } from 'express';
import Joi from 'joi';

export const validateRequest = (schema: Joi.ObjectSchema) => {
  return (req: Request, res: Response, next: NextFunction) => {
    const { error } = schema.validate(req.body);
    
    if (error) {
      return res.status(400).json({
        error: 'Validation Error',
        message: error.details[0].message,
        details: error.details
      });
    }
    
    next();
  };
};

// Job validation schemas
export const jobCreateSchema = Joi.object({
  company: Joi.string().required().min(1).max(100).messages({
    'string.empty': 'Company name is required',
    'string.min': 'Company name must be at least 1 character',
    'string.max': 'Company name cannot exceed 100 characters'
  }),
  position: Joi.string().required().min(1).max(100).messages({
    'string.empty': 'Position is required',
    'string.min': 'Position must be at least 1 character',
    'string.max': 'Position cannot exceed 100 characters'
  }),
  status: Joi.string().valid('applied', 'interviewing', 'offered', 'rejected', 'withdrawn').required().messages({
    'any.only': 'Status must be one of: applied, interviewing, offered, rejected, withdrawn'
  }),
  dateApplied: Joi.date().iso().required().messages({
    'date.base': 'Date applied must be a valid date',
    'date.format': 'Date applied must be in ISO format'
  }),
  jobUrl: Joi.string().uri().optional().allow('').messages({
    'string.uri': 'Job URL must be a valid URL'
  }),
  description: Joi.string().optional().max(1000).messages({
    'string.max': 'Description cannot exceed 1000 characters'
  }),
  salary: Joi.number().positive().optional().messages({
    'number.positive': 'Salary must be a positive number'
  }),
  location: Joi.string().optional().max(100).messages({
    'string.max': 'Location cannot exceed 100 characters'
  }),
  notes: Joi.string().optional().max(2000).messages({
    'string.max': 'Notes cannot exceed 2000 characters'
  })
});

export const jobUpdateSchema = Joi.object({
  company: Joi.string().min(1).max(100).optional(),
  position: Joi.string().min(1).max(100).optional(),
  status: Joi.string().valid('applied', 'interviewing', 'offered', 'rejected', 'withdrawn').optional(),
  dateApplied: Joi.date().iso().optional(),
  jobUrl: Joi.string().uri().optional().allow(''),
  description: Joi.string().max(1000).optional(),
  salary: Joi.number().positive().optional(),
  location: Joi.string().max(100).optional(),
  notes: Joi.string().max(2000).optional()
});

// User validation schemas
export const userRegistrationSchema = Joi.object({
  name: Joi.string().required().min(2).max(50).messages({
    'string.empty': 'Name is required',
    'string.min': 'Name must be at least 2 characters',
    'string.max': 'Name cannot exceed 50 characters'
  }),
  email: Joi.string().email().required().messages({
    'string.email': 'Please provide a valid email address',
    'string.empty': 'Email is required'
  }),
  password: Joi.string().min(6).required().messages({
    'string.min': 'Password must be at least 6 characters',
    'string.empty': 'Password is required'
  })
});

export const userLoginSchema = Joi.object({
  email: Joi.string().email().required().messages({
    'string.email': 'Please provide a valid email address',
    'string.empty': 'Email is required'
  }),
  password: Joi.string().required().messages({
    'string.empty': 'Password is required'
  })
});

// Query parameter validation
export const jobQuerySchema = Joi.object({
  page: Joi.number().integer().min(1).optional().default(1),
  limit: Joi.number().integer().min(1).max(100).optional().default(10),
  status: Joi.string().valid('applied', 'interviewing', 'offered', 'rejected', 'withdrawn').optional(),
  company: Joi.string().optional(),
  position: Joi.string().optional(),
  dateFrom: Joi.date().iso().optional(),
  dateTo: Joi.date().iso().optional(),
  sortBy: Joi.string().valid('dateApplied', 'company', 'position', 'status', 'createdAt').optional().default('dateApplied'),
  sortOrder: Joi.string().valid('asc', 'desc').optional().default('desc')
});

export const validateQuery = (schema: Joi.ObjectSchema) => {
  return (req: Request, res: Response, next: NextFunction) => {
    const { error, value } = schema.validate(req.query);
    
    if (error) {
      return res.status(400).json({
        error: 'Query Validation Error',
        message: error.details[0].message,
        details: error.details
      });
    }
    
    req.query = value;
    next();
  };
};
