import { Request, Response } from 'express';
import { getRepository, Like, Between } from 'typeorm';
import { Job } from '../models/JobEnhanced';
import { AuthRequest } from '../middleware/auth';

export class JobController {
  private jobRepository = getRepository(Job);

  // Get all jobs with filtering and search
  async getAllJobs(req: AuthRequest, res: Response) {
    try {
      const userId = req.user?.id;
      const {
        page = 1,
        limit = 10,
        status,
        company,
        position,
        dateFrom,
        dateTo,
        sortBy = 'dateApplied',
        sortOrder = 'desc',
        search
      } = req.query;

      const skip = (Number(page) - 1) * Number(limit);
      
      // Build where conditions
      const whereConditions: any = { userId };

      if (status) {
        whereConditions.status = status;
      }

      if (company) {
        whereConditions.company = Like(`%${company}%`);
      }

      if (position) {
        whereConditions.position = Like(`%${position}%`);
      }

      if (dateFrom && dateTo) {
        whereConditions.dateApplied = Between(new Date(dateFrom as string), new Date(dateTo as string));
      } else if (dateFrom) {
        whereConditions.dateApplied = Between(new Date(dateFrom as string), new Date());
      }

      // Build query
      let queryBuilder = this.jobRepository.createQueryBuilder('job')
        .where(whereConditions)
        .leftJoinAndSelect('job.user', 'user');

      // Add search functionality
      if (search) {
        queryBuilder = queryBuilder.andWhere(
          '(job.company ILIKE :search OR job.position ILIKE :search OR job.location ILIKE :search OR job.description ILIKE :search)',
          { search: `%${search}%` }
        );
      }

      // Add sorting
      queryBuilder = queryBuilder.orderBy(`job.${sortBy}`, sortOrder.toUpperCase() as 'ASC' | 'DESC');

      // Add pagination
      queryBuilder = queryBuilder.skip(skip).take(Number(limit));

      const [jobs, total] = await queryBuilder.getManyAndCount();

      // Calculate statistics
      const stats = await this.getJobStatistics(userId);

      res.json({
        jobs: jobs.map(job => job.toSummary()),
        pagination: {
          page: Number(page),
          limit: Number(limit),
          total,
          totalPages: Math.ceil(total / Number(limit))
        },
        statistics: stats
      });
    } catch (error) {
      console.error('Get jobs error:', error);
      res.status(500).json({
        error: 'Failed to fetch jobs',
        message: 'An error occurred while fetching jobs'
      });
    }
  }

  // Get job by ID
  async getJobById(req: AuthRequest, res: Response) {
    try {
      const { id } = req.params;
      const userId = req.user?.id;

      const job = await this.jobRepository.findOne({
        where: { id, userId },
        relations: ['user']
      });

      if (!job) {
        return res.status(404).json({
          error: 'Job not found',
          message: 'Job application not found'
        });
      }

      res.json({ job });
    } catch (error) {
      console.error('Get job error:', error);
      res.status(500).json({
        error: 'Failed to fetch job',
        message: 'An error occurred while fetching job'
      });
    }
  }

  // Create new job
  async createJob(req: AuthRequest, res: Response) {
    try {
      const userId = req.user?.id;
      const jobData = req.body;

      const job = this.jobRepository.create({
        ...jobData,
        userId,
        dateApplied: new Date(jobData.dateApplied)
      });

      const savedJob = await this.jobRepository.save(job);

      res.status(201).json({
        message: 'Job application created successfully',
        job: savedJob.toSummary()
      });
    } catch (error) {
      console.error('Create job error:', error);
      res.status(500).json({
        error: 'Failed to create job',
        message: 'An error occurred while creating job application'
      });
    }
  }

  // Update job
  async updateJob(req: AuthRequest, res: Response) {
    try {
      const { id } = req.params;
      const userId = req.user?.id;
      const updateData = req.body;

      const job = await this.jobRepository.findOne({
        where: { id, userId }
      });

      if (!job) {
        return res.status(404).json({
          error: 'Job not found',
          message: 'Job application not found'
        });
      }

      // Update job fields
      Object.assign(job, updateData);

      if (updateData.dateApplied) {
        job.dateApplied = new Date(updateData.dateApplied);
      }

      const updatedJob = await this.jobRepository.save(job);

      res.json({
        message: 'Job application updated successfully',
        job: updatedJob.toSummary()
      });
    } catch (error) {
      console.error('Update job error:', error);
      res.status(500).json({
        error: 'Failed to update job',
        message: 'An error occurred while updating job application'
      });
    }
  }

  // Delete job
  async deleteJob(req: AuthRequest, res: Response) {
    try {
      const { id } = req.params;
      const userId = req.user?.id;

      const job = await this.jobRepository.findOne({
        where: { id, userId }
      });

      if (!job) {
        return res.status(404).json({
          error: 'Job not found',
          message: 'Job application not found'
        });
      }

      await this.jobRepository.remove(job);

      res.json({
        message: 'Job application deleted successfully'
      });
    } catch (error) {
      console.error('Delete job error:', error);
      res.status(500).json({
        error: 'Failed to delete job',
        message: 'An error occurred while deleting job application'
      });
    }
  }

  // Get job statistics
  async getJobStatistics(userId: string) {
    try {
      const totalJobs = await this.jobRepository.count({ where: { userId } });
      
      const statusCounts = await this.jobRepository
        .createQueryBuilder('job')
        .select('job.status', 'status')
        .addSelect('COUNT(*)', 'count')
        .where('job.userId = :userId', { userId })
        .groupBy('job.status')
        .getRawMany();

      const recentJobs = await this.jobRepository.count({
        where: {
          userId,
          dateApplied: Between(
            new Date(Date.now() - 7 * 24 * 60 * 60 * 1000), // 7 days ago
            new Date()
          )
        }
      });

      const stats = {
        total: totalJobs,
        recent: recentJobs,
        byStatus: statusCounts.reduce((acc, item) => {
          acc[item.status] = parseInt(item.count);
          return acc;
        }, {} as Record<string, number>)
      };

      return stats;
    } catch (error) {
      console.error('Statistics error:', error);
      return {
        total: 0,
        recent: 0,
        byStatus: {}
      };
    }
  }

  // Get dashboard statistics
  async getDashboardStats(req: AuthRequest, res: Response) {
    try {
      const userId = req.user?.id;
      const stats = await this.getJobStatistics(userId);

      res.json({ statistics: stats });
    } catch (error) {
      console.error('Dashboard stats error:', error);
      res.status(500).json({
        error: 'Failed to fetch statistics',
        message: 'An error occurred while fetching dashboard statistics'
      });
    }
  }
}
