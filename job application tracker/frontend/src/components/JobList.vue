<template>
  <div class="job-list-container">
    <!-- Header with Search and Filters -->
    <div class="bg-white shadow-soft rounded-lg p-6 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-4 lg:mb-0">
          Job Applications
          <span class="text-sm font-normal text-gray-500 ml-2">
            ({{ statistics.total }} total)
          </span>
        </h1>
        
        <button
          @click="showCreateModal = true"
          class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200 flex items-center"
        >
          <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
          Add Job Application
        </button>
      </div>

      <!-- Search and Filters -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Search -->
        <div class="relative">
          <input
            v-model="filters.search"
            @input="debouncedSearch"
            type="text"
            placeholder="Search jobs..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
          <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Status Filter -->
        <select
          v-model="filters.status"
          @change="applyFilters"
          class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        >
          <option value="">All Statuses</option>
          <option v-for="status in statusOptions" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>

        <!-- Sort By -->
        <select
          v-model="filters.sortBy"
          @change="applyFilters"
          class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        >
          <option v-for="sort in sortOptions" :key="sort.value" :value="sort.value">
            Sort by {{ sort.label }}
          </option>
        </select>

        <!-- Sort Order -->
        <select
          v-model="filters.sortOrder"
          @change="applyFilters"
          class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
        >
          <option value="desc">Newest First</option>
          <option value="asc">Oldest First</option>
        </select>
      </div>

      <!-- Active Filters -->
      <div v-if="hasActiveFilters" class="mt-4 flex flex-wrap gap-2">
        <span class="text-sm text-gray-600">Active filters:</span>
        <span
          v-if="filters.search"
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800"
        >
          Search: "{{ filters.search }}"
          <button @click="clearFilter('search')" class="ml-1 text-primary-600 hover:text-primary-800">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </span>
        <span
          v-if="filters.status"
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary-100 text-primary-800"
        >
          Status: {{ getStatusLabel(filters.status) }}
          <button @click="clearFilter('status')" class="ml-1 text-primary-600 hover:text-primary-800">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </span>
        <button
          @click="clearAllFilters"
          class="text-sm text-primary-600 hover:text-primary-800 font-medium"
        >
          Clear all
        </button>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <div class="bg-white p-6 rounded-lg shadow-soft">
        <div class="flex items-center">
          <div class="p-2 bg-blue-100 rounded-lg">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Total Applications</p>
            <p class="text-2xl font-semibold text-gray-900">{{ statistics.total }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-lg shadow-soft">
        <div class="flex items-center">
          <div class="p-2 bg-green-100 rounded-lg">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Recent (7 days)</p>
            <p class="text-2xl font-semibold text-gray-900">{{ statistics.recent }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-lg shadow-soft">
        <div class="flex items-center">
          <div class="p-2 bg-yellow-100 rounded-lg">
            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Interviewing</p>
            <p class="text-2xl font-semibold text-gray-900">{{ statistics.byStatus.interviewing || 0 }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 rounded-lg shadow-soft">
        <div class="flex items-center">
          <div class="p-2 bg-purple-100 rounded-lg">
            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-600">Success Rate</p>
            <p class="text-2xl font-semibold text-gray-900">{{ successRate }}%</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-12">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Job Cards -->
    <div v-else-if="jobs.length > 0" class="space-y-4">
      <div
        v-for="job in jobs"
        :key="job.id"
        class="bg-white rounded-lg shadow-soft hover:shadow-medium transition-shadow duration-200 p-6"
      >
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
          <div class="flex-1">
            <div class="flex items-start justify-between mb-2">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ job.position }}</h3>
                <p class="text-gray-600">{{ job.company }}</p>
              </div>
              <span
                :class="getStatusBadgeClass(job.status)"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
              >
                {{ getStatusLabel(job.status) }}
              </span>
            </div>
            
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 mb-3">
              <span v-if="job.location" class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {{ job.location }}
              </span>
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z" />
                </svg>
                Applied {{ formatDate(job.dateApplied) }}
              </span>
              <span v-if="job.salary" class="flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                {{ formatSalary(job.salary) }}
              </span>
              <span v-if="job.isRecent" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                New
              </span>
            </div>
          </div>

          <div class="flex items-center space-x-2 mt-4 lg:mt-0">
            <button
              @click="viewJob(job)"
              class="text-primary-600 hover:text-primary-800 font-medium text-sm"
            >
              View
            </button>
            <button
              @click="editJob(job)"
              class="text-gray-600 hover:text-gray-800 font-medium text-sm"
            >
              Edit
            </button>
            <button
              @click="deleteJob(job)"
              class="text-red-600 hover:text-red-800 font-medium text-sm"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900">No job applications</h3>
      <p class="mt-1 text-sm text-gray-500">Get started by creating your first job application.</p>
      <div class="mt-6">
        <button
          @click="showCreateModal = true"
          class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors duration-200"
        >
          Add Job Application
        </button>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.totalPages > 1" class="mt-8 flex justify-center">
      <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
        <button
          @click="changePage(pagination.page - 1)"
          :disabled="pagination.page === 1"
          class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Previous
        </button>
        
        <button
          v-for="page in visiblePages"
          :key="page"
          @click="changePage(page)"
          :class="[
            'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
            page === pagination.page
              ? 'z-10 bg-primary-50 border-primary-500 text-primary-600'
              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
          ]"
        >
          {{ page }}
        </button>
        
        <button
          @click="changePage(pagination.page + 1)"
          :disabled="pagination.page === pagination.totalPages"
          class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Next
        </button>
      </nav>
    </div>
  </div>
</template>

<script lang="ts">
import { Component, Vue } from 'vue-property-decorator';
import jobService, { Job, JobFilters, JobStatistics, JobStatus } from '@/services/jobService';

@Component
export default class JobList extends Vue {
  jobs: Job[] = [];
  loading = false;
  showCreateModal = false;
  
  filters: JobFilters = {
    page: 1,
    limit: 10,
    search: '',
    status: '',
    sortBy: 'dateApplied',
    sortOrder: 'desc'
  };

  pagination = {
    page: 1,
    limit: 10,
    total: 0,
    totalPages: 0
  };

  statistics: JobStatistics = {
    total: 0,
    recent: 0,
    byStatus: {} as Record<JobStatus, number>
  };

  statusOptions = jobService.getStatusOptions();
  sortOptions = jobService.getSortOptions();

  private searchTimeout: number | null = null;

  async mounted() {
    await this.loadJobs();
  }

  async loadJobs() {
    this.loading = true;
    try {
      const response = await jobService.getAllJobs(this.filters);
      this.jobs = response.jobs;
      this.pagination = response.pagination;
      this.statistics = response.statistics;
    } catch (error) {
      this.$toast.error('Failed to load jobs');
      console.error('Load jobs error:', error);
    } finally {
      this.loading = false;
    }
  }

  debouncedSearch() {
    if (this.searchTimeout) {
      clearTimeout(this.searchTimeout);
    }
    this.searchTimeout = window.setTimeout(() => {
      this.applyFilters();
    }, 500);
  }

  async applyFilters() {
    this.filters.page = 1;
    await this.loadJobs();
  }

  async changePage(page: number) {
    if (page >= 1 && page <= this.pagination.totalPages) {
      this.filters.page = page;
      await this.loadJobs();
    }
  }

  clearFilter(filterName: keyof JobFilters) {
    (this.filters as any)[filterName] = '';
    this.applyFilters();
  }

  clearAllFilters() {
    this.filters = {
      page: 1,
      limit: 10,
      search: '',
      status: '',
      sortBy: 'dateApplied',
      sortOrder: 'desc'
    };
    this.applyFilters();
  }

  get hasActiveFilters(): boolean {
    return !!(this.filters.search || this.filters.status);
  }

  get visiblePages(): number[] {
    const current = this.pagination.page;
    const total = this.pagination.totalPages;
    const pages: number[] = [];

    if (total <= 7) {
      for (let i = 1; i <= total; i++) {
        pages.push(i);
      }
    } else {
      if (current <= 4) {
        for (let i = 1; i <= 5; i++) {
          pages.push(i);
        }
        pages.push(-1, total);
      } else if (current >= total - 3) {
        pages.push(1, -1);
        for (let i = total - 4; i <= total; i++) {
          pages.push(i);
        }
      } else {
        pages.push(1, -1);
        for (let i = current - 1; i <= current + 1; i++) {
          pages.push(i);
        }
        pages.push(-1, total);
      }
    }

    return pages;
  }

  get successRate(): number {
    const total = this.statistics.total;
    if (total === 0) return 0;
    
    const successful = (this.statistics.byStatus.offered || 0);
    return Math.round((successful / total) * 100);
  }

  getStatusLabel(status: JobStatus): string {
    const option = this.statusOptions.find(opt => opt.value === status);
    return option ? option.label : status;
  }

  getStatusBadgeClass(status: JobStatus): string {
    return jobService.getStatusBadgeClass(status);
  }

  formatDate(date: string): string {
    return jobService.formatDate(date);
  }

  formatSalary(salary?: number): string {
    return jobService.formatSalary(salary);
  }

  viewJob(job: Job) {
    this.$router.push(`/jobs/${job.id}`);
  }

  editJob(job: Job) {
    this.$router.push(`/jobs/${job.id}/edit`);
  }

  async deleteJob(job: Job) {
    if (confirm(`Are you sure you want to delete the application for ${job.position} at ${job.company}?`)) {
      try {
        await jobService.deleteJob(job.id);
        this.$toast.success('Job application deleted successfully');
        await this.loadJobs();
      } catch (error) {
        this.$toast.error('Failed to delete job application');
        console.error('Delete job error:', error);
      }
    }
  }
}
</script>

<style scoped>
.job-list-container {
  @apply max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8;
}
</style>
