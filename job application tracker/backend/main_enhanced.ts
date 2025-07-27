import 'reflect-metadata';
import { fixModuleAlias } from './src/utils/fix-module-alias';
fixModuleAlias(__dirname);
import { appConfig } from '@base/config/app';
import { useContainer as routingControllersUseContainer, useExpressServer } from 'routing-controllers';
import { loadHelmet } from '@base/src/utils/load-helmet';
import { Container } from 'typedi';
import { createConnection, useContainer as typeormUseContainer } from 'typeorm';
import { Container as containerTypeorm } from 'typeorm-typedi-extensions';
import { useSocketServer, useContainer as socketUseContainer } from 'socket-controllers';
import express from 'express';
import bodyParser from 'body-parser';
import cors from 'cors';
import dotenv from 'dotenv';
import { errorHandler, notFound } from './src/middleware/errorHandler';
import authRoutes from './src/routes/auth';
import jobRoutes from './src/routes/jobs';

// Load environment variables
dotenv.config();

export class App {
  private app: express.Application = express();
  private port: Number = appConfig.port;

  public constructor() {
    this.bootstrap();
  }

  private async bootstrap() {
    this.useContainers();
    await this.setupConnection();
    this.setupMiddlewares();
    this.setupRoutes();
    this.setupErrorHandling();
    this.registerSocketControllers();
    this.registerRoutingControllers();
    this.registerDefaultHomePage();
  }

  private useContainers(): void {
    routingControllersUseContainer(Container);
    typeormUseContainer(containerTypeorm);
    socketUseContainer(Container);
  }

  private async setupConnection() {
    try {
      await createConnection();
      console.log('✅ Database connected successfully');
    } catch (error) {
      console.log('❌ Cannot connect to database: ', error);
      process.exit(1);
    }
  }

  private setupMiddlewares() {
    // CORS configuration
    this.app.use(cors({
      origin: process.env.FRONTEND_URL || 'http://localhost:8080',
      credentials: true,
      methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
      allowedHeaders: ['Content-Type', 'Authorization']
    }));

    // Body parsing middleware
    this.app.use(bodyParser.urlencoded({ extended: true }));
    this.app.use(bodyParser.json({ limit: '10mb' }));

    // Security middleware
    loadHelmet(this.app);

    // Request logging middleware
    this.app.use((req, res, next) => {
      console.log(`${new Date().toISOString()} - ${req.method} ${req.path}`);
      next();
    });
  }

  private setupRoutes() {
    // API routes
    this.app.use('/api/auth', authRoutes);
    this.app.use('/api/jobs', jobRoutes);

    // Health check endpoint
    this.app.get('/health', (req, res) => {
      res.json({
        status: 'OK',
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        environment: process.env.NODE_ENV || 'development'
      });
    });

    // API documentation endpoint
    this.app.get('/api', (req, res) => {
      res.json({
        name: 'Job Application Tracker API',
        version: '1.0.0',
        description: 'RESTful API for managing job applications with authentication',
        endpoints: {
          auth: {
            'POST /api/auth/register': 'Register new user',
            'POST /api/auth/login': 'Login user',
            'GET /api/auth/profile': 'Get user profile',
            'PUT /api/auth/profile': 'Update user profile',
            'POST /api/auth/change-password': 'Change password',
            'POST /api/auth/logout': 'Logout user'
          },
          jobs: {
            'GET /api/jobs': 'Get all jobs with filtering and search',
            'GET /api/jobs/statistics': 'Get dashboard statistics',
            'GET /api/jobs/:id': 'Get job by ID',
            'POST /api/jobs': 'Create new job',
            'PUT /api/jobs/:id': 'Update job',
            'DELETE /api/jobs/:id': 'Delete job'
          }
        },
        authentication: 'Bearer token required for protected routes',
        documentation: 'https://github.com/mahi-88/job-application-tracker'
      });
    });
  }

  private setupErrorHandling() {
    // 404 handler
    this.app.use(notFound);

    // Global error handler
    this.app.use(errorHandler);
  }

  private registerSocketControllers() {
    const server = require('http').Server(this.app);
    const io = require('socket.io')(server, {
      cors: {
        origin: process.env.FRONTEND_URL || 'http://localhost:8080',
        methods: ['GET', 'POST']
      }
    });

    this.app.use(function(req: any, res: any, next) {
      req.io = io;
      next();
    });

    server.listen(this.port, () => {
      console.log(`🚀 Server started at http://localhost:${this.port}`);
      console.log(`🌍 Environment: ${process.env.NODE_ENV || 'development'}`);
      console.log(`📚 API Documentation: http://localhost:${this.port}/api`);
      console.log(`❤️  Health Check: http://localhost:${this.port}/health`);
    });

    useSocketServer(io, {
      controllers: [__dirname + appConfig.controllersDir],
    });

    // Socket.io connection handling
    io.on('connection', (socket: any) => {
      console.log('👤 User connected:', socket.id);

      socket.on('disconnect', () => {
        console.log('👋 User disconnected:', socket.id);
      });

      // Real-time job updates
      socket.on('job-updated', (data: any) => {
        socket.broadcast.emit('job-updated', data);
      });
    });
  }

  private registerRoutingControllers() {
    useExpressServer(this.app, {
      validation: { stopAtFirstError: true },
      cors: {
        origin: process.env.FRONTEND_URL || 'http://localhost:8080',
        methods: 'GET,PUT,POST,DELETE',
        credentials: true
      },
      classTransformer: true,
      defaultErrorHandler: false,
      routePrefix: appConfig.routePrefix,
      controllers: [__dirname + appConfig.controllersDir],
      middlewares: [__dirname + appConfig.middlewaresDir],
    });
  }

  private registerDefaultHomePage() {
    this.app.get('/', (req, res) => {
      res.json({
        title: appConfig.name,
        version: '1.0.0',
        description: 'Job Application Tracker API with Authentication',
        mode: appConfig.appEnv,
        date: new Date(),
        endpoints: {
          api: '/api',
          health: '/health',
          documentation: 'https://github.com/mahi-88/job-application-tracker'
        }
      });
    });
  }
}

new App();
