import { shallowMount, createLocalVue } from '@vue/test-utils';
import JobList from '@/components/JobList.vue';
import jobService from '@/services/jobService';
import VueRouter from 'vue-router';

// Mock the job service
jest.mock('@/services/jobService');
const mockJobService = jobService as jest.Mocked<typeof jobService>;

const localVue = createLocalVue();
localVue.use(VueRouter);

describe('JobList.vue', () => {
  let wrapper: any;
  let router: VueRouter;

  const mockJobs = [
    {
      id: '1',
      position: 'Frontend Developer',
      company: 'TechCorp',
      status: 'applied',
      location: 'San Francisco, CA',
      dateApplied: '2024-01-15',
      salary: 120000,
      daysApplied: 5,
      isRecent: true,
      statusColor: '#3B82F6',
      createdAt: '2024-01-15T10:00:00Z',
      updatedAt: '2024-01-15T10:00:00Z'
    },
    {
      id: '2',
      position: 'Backend Developer',
      company: 'DataCorp',
      status: 'interviewing',
      location: 'New York, NY',
      dateApplied: '2024-01-10',
      salary: 130000,
      daysApplied: 10,
      isRecent: false,
      statusColor: '#F59E0B',
      createdAt: '2024-01-10T10:00:00Z',
      updatedAt: '2024-01-10T10:00:00Z'
    }
  ];

  const mockResponse = {
    jobs: mockJobs,
    pagination: {
      page: 1,
      limit: 10,
      total: 2,
      totalPages: 1
    },
    statistics: {
      total: 2,
      recent: 1,
      byStatus: {
        applied: 1,
        interviewing: 1,
        offered: 0,
        rejected: 0,
        withdrawn: 0
      }
    }
  };

  beforeEach(() => {
    router = new VueRouter();
    mockJobService.getAllJobs.mockResolvedValue(mockResponse);
    mockJobService.getStatusOptions.mockReturnValue([
      { value: 'applied', label: 'Applied', color: '#3B82F6' },
      { value: 'interviewing', label: 'Interviewing', color: '#F59E0B' }
    ]);
    mockJobService.getSortOptions.mockReturnValue([
      { value: 'dateApplied', label: 'Date Applied' },
      { value: 'company', label: 'Company' }
    ]);

    wrapper = shallowMount(JobList, {
      localVue,
      router,
      mocks: {
        $toast: {
          success: jest.fn(),
          error: jest.fn()
        }
      }
    });
  });

  afterEach(() => {
    wrapper.destroy();
    jest.clearAllMocks();
  });

  describe('Component Initialization', () => {
    it('should render correctly', () => {
      expect(wrapper.exists()).toBe(true);
      expect(wrapper.find('.job-list-container').exists()).toBe(true);
    });

    it('should load jobs on mount', async () => {
      await wrapper.vm.$nextTick();
      expect(mockJobService.getAllJobs).toHaveBeenCalled();
      expect(wrapper.vm.jobs).toEqual(mockJobs);
    });

    it('should display statistics correctly', async () => {
      await wrapper.vm.$nextTick();
      expect(wrapper.vm.statistics.total).toBe(2);
      expect(wrapper.vm.statistics.recent).toBe(1);
    });
  });

  describe('Search and Filtering', () => {
    it('should update search filter', async () => {
      const searchInput = wrapper.find('input[placeholder="Search jobs..."]');
      await searchInput.setValue('TechCorp');
      
      expect(wrapper.vm.filters.search).toBe('TechCorp');
    });

    it('should apply status filter', async () => {
      const statusSelect = wrapper.findAll('select').at(0);
      await statusSelect.setValue('interviewing');
      
      expect(wrapper.vm.filters.status).toBe('interviewing');
      expect(mockJobService.getAllJobs).toHaveBeenCalledWith(
        expect.objectContaining({
          status: 'interviewing'
        })
      );
    });

    it('should clear individual filters', async () => {
      wrapper.vm.filters.search = 'test';
      wrapper.vm.filters.status = 'applied';
      
      wrapper.vm.clearFilter('search');
      expect(wrapper.vm.filters.search).toBe('');
      
      wrapper.vm.clearFilter('status');
      expect(wrapper.vm.filters.status).toBe('');
    });

    it('should clear all filters', async () => {
      wrapper.vm.filters.search = 'test';
      wrapper.vm.filters.status = 'applied';
      
      wrapper.vm.clearAllFilters();
      
      expect(wrapper.vm.filters.search).toBe('');
      expect(wrapper.vm.filters.status).toBe('');
      expect(wrapper.vm.filters.sortBy).toBe('dateApplied');
    });

    it('should detect active filters', () => {
      wrapper.vm.filters.search = '';
      wrapper.vm.filters.status = '';
      expect(wrapper.vm.hasActiveFilters).toBe(false);
      
      wrapper.vm.filters.search = 'test';
      expect(wrapper.vm.hasActiveFilters).toBe(true);
      
      wrapper.vm.filters.search = '';
      wrapper.vm.filters.status = 'applied';
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });
  });

  describe('Pagination', () => {
    beforeEach(() => {
      wrapper.vm.pagination = {
        page: 2,
        limit: 10,
        total: 25,
        totalPages: 3
      };
    });

    it('should calculate visible pages correctly', () => {
      const visiblePages = wrapper.vm.visiblePages;
      expect(visiblePages).toContain(1);
      expect(visiblePages).toContain(2);
      expect(visiblePages).toContain(3);
    });

    it('should change page correctly', async () => {
      await wrapper.vm.changePage(3);
      expect(wrapper.vm.filters.page).toBe(3);
      expect(mockJobService.getAllJobs).toHaveBeenCalledWith(
        expect.objectContaining({
          page: 3
        })
      );
    });

    it('should not change to invalid page', async () => {
      const currentPage = wrapper.vm.filters.page;
      await wrapper.vm.changePage(0);
      expect(wrapper.vm.filters.page).toBe(currentPage);
      
      await wrapper.vm.changePage(10);
      expect(wrapper.vm.filters.page).toBe(currentPage);
    });
  });

  describe('Job Actions', () => {
    it('should navigate to job view', () => {
      const routerPushSpy = jest.spyOn(router, 'push');
      wrapper.vm.viewJob(mockJobs[0]);
      expect(routerPushSpy).toHaveBeenCalledWith('/jobs/1');
    });

    it('should navigate to job edit', () => {
      const routerPushSpy = jest.spyOn(router, 'push');
      wrapper.vm.editJob(mockJobs[0]);
      expect(routerPushSpy).toHaveBeenCalledWith('/jobs/1/edit');
    });

    it('should delete job with confirmation', async () => {
      window.confirm = jest.fn().mockReturnValue(true);
      mockJobService.deleteJob.mockResolvedValue(undefined);
      
      await wrapper.vm.deleteJob(mockJobs[0]);
      
      expect(window.confirm).toHaveBeenCalledWith(
        'Are you sure you want to delete the application for Frontend Developer at TechCorp?'
      );
      expect(mockJobService.deleteJob).toHaveBeenCalledWith('1');
      expect(wrapper.vm.$toast.success).toHaveBeenCalledWith('Job application deleted successfully');
    });

    it('should not delete job without confirmation', async () => {
      window.confirm = jest.fn().mockReturnValue(false);
      
      await wrapper.vm.deleteJob(mockJobs[0]);
      
      expect(mockJobService.deleteJob).not.toHaveBeenCalled();
    });

    it('should handle delete error', async () => {
      window.confirm = jest.fn().mockReturnValue(true);
      mockJobService.deleteJob.mockRejectedValue(new Error('Delete failed'));
      
      await wrapper.vm.deleteJob(mockJobs[0]);
      
      expect(wrapper.vm.$toast.error).toHaveBeenCalledWith('Failed to delete job application');
    });
  });

  describe('Utility Methods', () => {
    it('should format dates correctly', () => {
      const formattedDate = wrapper.vm.formatDate('2024-01-15');
      expect(formattedDate).toMatch(/Jan 15, 2024/);
    });

    it('should format salary correctly', () => {
      expect(wrapper.vm.formatSalary(120000)).toBe('$120,000');
      expect(wrapper.vm.formatSalary()).toBe('Not specified');
    });

    it('should get status label correctly', () => {
      expect(wrapper.vm.getStatusLabel('applied')).toBe('Applied');
      expect(wrapper.vm.getStatusLabel('interviewing')).toBe('Interviewing');
    });

    it('should calculate success rate correctly', () => {
      wrapper.vm.statistics = {
        total: 10,
        recent: 2,
        byStatus: {
          applied: 5,
          interviewing: 2,
          offered: 2,
          rejected: 1,
          withdrawn: 0
        }
      };
      
      expect(wrapper.vm.successRate).toBe(20); // 2 offered out of 10 total
    });

    it('should handle zero total for success rate', () => {
      wrapper.vm.statistics = {
        total: 0,
        recent: 0,
        byStatus: {}
      };
      
      expect(wrapper.vm.successRate).toBe(0);
    });
  });

  describe('Loading States', () => {
    it('should show loading spinner when loading', async () => {
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();
      
      expect(wrapper.find('.animate-spin').exists()).toBe(true);
    });

    it('should show empty state when no jobs', async () => {
      wrapper.vm.jobs = [];
      wrapper.vm.loading = false;
      await wrapper.vm.$nextTick();
      
      expect(wrapper.text()).toContain('No job applications');
    });
  });

  describe('Error Handling', () => {
    it('should handle load jobs error', async () => {
      mockJobService.getAllJobs.mockRejectedValue(new Error('Network error'));
      
      await wrapper.vm.loadJobs();
      
      expect(wrapper.vm.$toast.error).toHaveBeenCalledWith('Failed to load jobs');
      expect(wrapper.vm.loading).toBe(false);
    });
  });
});
