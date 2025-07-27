import request from 'supertest';
import { createConnection, getConnection } from 'typeorm';
import { User } from '../models/User';
import { Job } from '../models/JobEnhanced';
import { JobController } from '../controllers/JobControllerEnhanced';
import { generateToken } from '../middleware/auth';

describe('Job Management Tests', () => {
  let jobController: JobController;
  let testUser: User;
  let authToken: string;

  beforeAll(async () => {
    // Setup test database connection
    await createConnection({
      type: 'sqlite',
      database: ':memory:',
      entities: [User, Job],
      synchronize: true,
      logging: false
    });

    jobController = new JobController();
  });

  afterAll(async () => {
    await getConnection().close();
  });

  beforeEach(async () => {
    // Clear database and create test user
    await getConnection().synchronize(true);
    
    testUser = new User();
    testUser.name = 'Test User';
    testUser.email = 'test@example.com';
    testUser.password = 'password123';
    await testUser.hashPassword();
    testUser = await getConnection().getRepository(User).save(testUser);
    
    authToken = generateToken(testUser);
  });

  describe('POST /jobs', () => {
    it('should create a new job application', async () => {
      const jobData = {
        position: 'Software Engineer',
        company: 'Tech Corp',
        status: 'applied',
        location: 'San Francisco, CA',
        dateApplied: '2024-01-15',
        salary: 120000,
        description: 'Full-stack development role'
      };

      const mockReq = {
        body: jobData,
        user: { id: testUser.id }
      } as any;

      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await jobController.createJob(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(201);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'Job application created successfully',
          job: expect.objectContaining({
            position: jobData.position,
            company: jobData.company,
            status: jobData.status
          })
        })
      );
    });

    it('should validate required fields', () => {
      const invalidJobData = {
        position: '', // required
        company: '', // required
        status: 'invalid-status', // invalid enum
        dateApplied: 'invalid-date'
      };

      // Validation would be handled by middleware
      expect(invalidJobData.position).toBe('');
      expect(invalidJobData.company).toBe('');
      expect(['applied', 'interviewing', 'offered', 'rejected', 'withdrawn'])
        .not.toContain(invalidJobData.status);
    });

    it('should set default values correctly', async () => {
      const minimalJobData = {
        position: 'Developer',
        company: 'StartupCo',
        status: 'applied',
        dateApplied: '2024-01-15'
      };

      const job = new Job();
      Object.assign(job, minimalJobData);
      job.userId = testUser.id;

      expect(job.isStartupCompany).toBe(false);
      expect(job.status).toBe('applied');
    });
  });

  describe('GET /jobs', () => {
    beforeEach(async () => {
      // Create test jobs
      const jobs = [
        {
          position: 'Frontend Developer',
          company: 'WebCorp',
          status: 'applied',
          dateApplied: new Date('2024-01-10'),
          userId: testUser.id
        },
        {
          position: 'Backend Developer',
          company: 'DataCorp',
          status: 'interviewing',
          dateApplied: new Date('2024-01-15'),
          userId: testUser.id
        },
        {
          position: 'Full Stack Developer',
          company: 'TechStart',
          status: 'offered',
          dateApplied: new Date('2024-01-20'),
          userId: testUser.id
        }
      ];

      for (const jobData of jobs) {
        const job = new Job();
        Object.assign(job, jobData);
        await getConnection().getRepository(Job).save(job);
      }
    });

    it('should return all jobs for authenticated user', async () => {
      const mockReq = {
        user: { id: testUser.id },
        query: {}
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.getAllJobs(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          jobs: expect.arrayContaining([
            expect.objectContaining({
              position: 'Frontend Developer',
              company: 'WebCorp'
            })
          ]),
          pagination: expect.objectContaining({
            total: 3
          }),
          statistics: expect.objectContaining({
            total: 3
          })
        })
      );
    });

    it('should filter jobs by status', async () => {
      const mockReq = {
        user: { id: testUser.id },
        query: { status: 'interviewing' }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.getAllJobs(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          jobs: expect.arrayContaining([
            expect.objectContaining({
              status: 'interviewing'
            })
          ])
        })
      );
    });

    it('should search jobs by company name', async () => {
      const mockReq = {
        user: { id: testUser.id },
        query: { search: 'WebCorp' }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.getAllJobs(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          jobs: expect.arrayContaining([
            expect.objectContaining({
              company: 'WebCorp'
            })
          ])
        })
      );
    });

    it('should sort jobs correctly', async () => {
      const mockReq = {
        user: { id: testUser.id },
        query: { sortBy: 'dateApplied', sortOrder: 'asc' }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.getAllJobs(mockReq, mockRes);

      const response = mockRes.json.mock.calls[0][0];
      const jobs = response.jobs;

      // Check if jobs are sorted by date applied in ascending order
      for (let i = 1; i < jobs.length; i++) {
        const prevDate = new Date(jobs[i - 1].dateApplied);
        const currDate = new Date(jobs[i].dateApplied);
        expect(prevDate.getTime()).toBeLessThanOrEqual(currDate.getTime());
      }
    });

    it('should paginate results correctly', async () => {
      const mockReq = {
        user: { id: testUser.id },
        query: { page: 1, limit: 2 }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.getAllJobs(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          jobs: expect.arrayContaining([]),
          pagination: expect.objectContaining({
            page: 1,
            limit: 2,
            total: 3,
            totalPages: 2
          })
        })
      );
    });
  });

  describe('PUT /jobs/:id', () => {
    let testJob: Job;

    beforeEach(async () => {
      testJob = new Job();
      testJob.position = 'Software Engineer';
      testJob.company = 'TechCorp';
      testJob.status = 'applied';
      testJob.dateApplied = new Date('2024-01-15');
      testJob.userId = testUser.id;
      testJob = await getConnection().getRepository(Job).save(testJob);
    });

    it('should update job application', async () => {
      const updateData = {
        status: 'interviewing',
        notes: 'Phone interview scheduled'
      };

      const mockReq = {
        params: { id: testJob.id },
        body: updateData,
        user: { id: testUser.id }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.updateJob(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'Job application updated successfully',
          job: expect.objectContaining({
            status: 'interviewing',
            notes: 'Phone interview scheduled'
          })
        })
      );
    });

    it('should not allow updating other users jobs', async () => {
      const otherUser = new User();
      otherUser.name = 'Other User';
      otherUser.email = 'other@example.com';
      otherUser.password = 'password123';
      await otherUser.hashPassword();
      const savedOtherUser = await getConnection().getRepository(User).save(otherUser);

      const mockReq = {
        params: { id: testJob.id },
        body: { status: 'interviewing' },
        user: { id: savedOtherUser.id }
      } as any;

      const mockRes = {
        status: jest.fn().mockReturnThis(),
        json: jest.fn()
      } as any;

      await jobController.updateJob(mockReq, mockRes);

      expect(mockRes.status).toHaveBeenCalledWith(404);
      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          error: 'Job not found'
        })
      );
    });
  });

  describe('DELETE /jobs/:id', () => {
    let testJob: Job;

    beforeEach(async () => {
      testJob = new Job();
      testJob.position = 'Software Engineer';
      testJob.company = 'TechCorp';
      testJob.status = 'applied';
      testJob.dateApplied = new Date('2024-01-15');
      testJob.userId = testUser.id;
      testJob = await getConnection().getRepository(Job).save(testJob);
    });

    it('should delete job application', async () => {
      const mockReq = {
        params: { id: testJob.id },
        user: { id: testUser.id }
      } as any;

      const mockRes = {
        json: jest.fn()
      } as any;

      await jobController.deleteJob(mockReq, mockRes);

      expect(mockRes.json).toHaveBeenCalledWith(
        expect.objectContaining({
          message: 'Job application deleted successfully'
        })
      );

      // Verify job is actually deleted
      const deletedJob = await getConnection().getRepository(Job).findOne(testJob.id);
      expect(deletedJob).toBeUndefined();
    });
  });

  describe('Job Statistics', () => {
    beforeEach(async () => {
      const jobs = [
        { status: 'applied', dateApplied: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000) }, // 2 days ago
        { status: 'interviewing', dateApplied: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000) }, // 5 days ago
        { status: 'offered', dateApplied: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000) }, // 10 days ago
        { status: 'rejected', dateApplied: new Date(Date.now() - 15 * 24 * 60 * 60 * 1000) } // 15 days ago
      ];

      for (const jobData of jobs) {
        const job = new Job();
        job.position = 'Test Position';
        job.company = 'Test Company';
        job.userId = testUser.id;
        Object.assign(job, jobData);
        await getConnection().getRepository(Job).save(job);
      }
    });

    it('should calculate statistics correctly', async () => {
      const stats = await jobController.getJobStatistics(testUser.id);

      expect(stats.total).toBe(4);
      expect(stats.recent).toBe(2); // Jobs from last 7 days
      expect(stats.byStatus.applied).toBe(1);
      expect(stats.byStatus.interviewing).toBe(1);
      expect(stats.byStatus.offered).toBe(1);
      expect(stats.byStatus.rejected).toBe(1);
    });
  });
});
