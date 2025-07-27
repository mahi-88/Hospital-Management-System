import axios, { AxiosResponse } from 'axios';

const API_BASE_URL = process.env.VUE_APP_API_URL || 'http://localhost:3000/api';

export type JobStatus = 'applied' | 'interviewing' | 'offered' | 'rejected' | 'withdrawn';

export interface Job {
  id: string;
  position: string;
  company: string;
  status: JobStatus;
  location?: string;
  description?: string;
  jobUrl?: string;
  salary?: number;
  dateApplied: string;
  notes?: string;
  isStartupCompany: boolean;
  contactPerson?: string;
  contactEmail?: string;
  contactPhone?: string;
  interviewDate?: string;
  source?: string;
  priority?: number;
  daysApplied: number;
  isRecent: boolean;
  statusColor: string;
  createdAt: string;
  updatedAt: string;
}

export interface JobFilters {
  page?: number;
  limit?: number;
  status?: JobStatus;
  company?: string;
  position?: string;
  dateFrom?: string;
  dateTo?: string;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
  search?: string;
}

export interface JobsResponse {
  jobs: Job[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
  statistics: JobStatistics;
}

export interface JobStatistics {
  total: number;
  recent: number;
  byStatus: Record<JobStatus, number>;
}

export interface CreateJobData {
  position: string;
  company: string;
  status: JobStatus;
  location?: string;
  description?: string;
  jobUrl?: string;
  salary?: number;
  dateApplied: string;
  notes?: string;
  isStartupCompany?: boolean;
  contactPerson?: string;
  contactEmail?: string;
  contactPhone?: string;
  interviewDate?: string;
  source?: string;
  priority?: number;
}

export interface UpdateJobData extends Partial<CreateJobData> {}

class JobService {
  async getAllJobs(filters: JobFilters = {}): Promise<JobsResponse> {
    try {
      const params = new URLSearchParams();
      
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          params.append(key, value.toString());
        }
      });

      const response: AxiosResponse<JobsResponse> = await axios.get(
        `${API_BASE_URL}/jobs?${params.toString()}`
      );

      return response.data;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getJobById(id: string): Promise<Job> {
    try {
      const response: AxiosResponse<{ job: Job }> = await axios.get(
        `${API_BASE_URL}/jobs/${id}`
      );

      return response.data.job;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async createJob(jobData: CreateJobData): Promise<Job> {
    try {
      const response: AxiosResponse<{ job: Job }> = await axios.post(
        `${API_BASE_URL}/jobs`,
        jobData
      );

      return response.data.job;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async updateJob(id: string, jobData: UpdateJobData): Promise<Job> {
    try {
      const response: AxiosResponse<{ job: Job }> = await axios.put(
        `${API_BASE_URL}/jobs/${id}`,
        jobData
      );

      return response.data.job;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async deleteJob(id: string): Promise<void> {
    try {
      await axios.delete(`${API_BASE_URL}/jobs/${id}`);
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  async getStatistics(): Promise<JobStatistics> {
    try {
      const response: AxiosResponse<{ statistics: JobStatistics }> = await axios.get(
        `${API_BASE_URL}/jobs/statistics`
      );

      return response.data.statistics;
    } catch (error: any) {
      throw this.handleError(error);
    }
  }

  // Helper methods for filtering and searching
  getStatusOptions(): Array<{ value: JobStatus; label: string; color: string }> {
    return [
      { value: 'applied', label: 'Applied', color: '#3B82F6' },
      { value: 'interviewing', label: 'Interviewing', color: '#F59E0B' },
      { value: 'offered', label: 'Offered', color: '#10B981' },
      { value: 'rejected', label: 'Rejected', color: '#EF4444' },
      { value: 'withdrawn', label: 'Withdrawn', color: '#6B7280' }
    ];
  }

  getSortOptions(): Array<{ value: string; label: string }> {
    return [
      { value: 'dateApplied', label: 'Date Applied' },
      { value: 'company', label: 'Company' },
      { value: 'position', label: 'Position' },
      { value: 'status', label: 'Status' },
      { value: 'createdAt', label: 'Created Date' }
    ];
  }

  getSourceOptions(): string[] {
    return [
      'LinkedIn',
      'Indeed',
      'Company Website',
      'Glassdoor',
      'AngelList',
      'Stack Overflow Jobs',
      'Referral',
      'Recruiter',
      'Job Fair',
      'Other'
    ];
  }

  // Utility methods
  formatSalary(salary?: number): string {
    if (!salary) return 'Not specified';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(salary);
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  getStatusBadgeClass(status: JobStatus): string {
    const classes = {
      applied: 'bg-blue-100 text-blue-800',
      interviewing: 'bg-yellow-100 text-yellow-800',
      offered: 'bg-green-100 text-green-800',
      rejected: 'bg-red-100 text-red-800',
      withdrawn: 'bg-gray-100 text-gray-800'
    };
    return classes[status] || classes.applied;
  }

  private handleError(error: any): any {
    if (error.response?.data) {
      return error.response.data;
    }

    if (error.request) {
      return {
        error: 'Network Error',
        message: 'Unable to connect to the server. Please check your internet connection.'
      };
    }

    return {
      error: 'Unknown Error',
      message: error.message || 'An unexpected error occurred'
    };
  }
}

export default new JobService();
