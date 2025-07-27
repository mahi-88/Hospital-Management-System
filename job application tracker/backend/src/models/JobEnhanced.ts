import { Column, Entity, PrimaryGeneratedColumn, BeforeInsert, ManyToOne, JoinColumn, CreateDateColumn, UpdateDateColumn } from 'typeorm';
import { BaseModel } from '@base/src/abstracts/BaseModel';
import { v4 as uuidv4 } from 'uuid';
import { User } from './User';
import { IsNotEmpty, IsUrl, IsOptional, IsNumber, IsDateString } from 'class-validator';

export type JobStatus = 'applied' | 'interviewing' | 'offered' | 'rejected' | 'withdrawn';

@Entity({ name: 'jobs' })
export class Job extends BaseModel {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'varchar', length: 100 })
  @IsNotEmpty({ message: 'Position is required' })
  position: string;

  @Column({ type: 'varchar', length: 100 })
  @IsNotEmpty({ message: 'Company is required' })
  company: string;

  @Column({ 
    type: 'enum',
    enum: ['applied', 'interviewing', 'offered', 'rejected', 'withdrawn'],
    default: 'applied'
  })
  status: JobStatus;

  @Column({ type: 'varchar', length: 100, nullable: true })
  @IsOptional()
  location: string;

  @Column({ type: 'text', nullable: true })
  @IsOptional()
  description: string;

  @Column({ type: 'text', nullable: true })
  @IsOptional()
  requirements: string;

  @Column({ type: 'varchar', length: 500, nullable: true })
  @IsOptional()
  @IsUrl({}, { message: 'Job URL must be a valid URL' })
  jobUrl: string;

  @Column({ type: 'decimal', precision: 10, scale: 2, nullable: true })
  @IsOptional()
  @IsNumber({}, { message: 'Salary must be a number' })
  salary: number;

  @Column({ type: 'date' })
  @IsDateString({}, { message: 'Date applied must be a valid date' })
  dateApplied: Date;

  @Column({ type: 'text', nullable: true })
  @IsOptional()
  notes: string;

  @Column({ type: 'boolean', default: false })
  isStartupCompany: boolean;

  @Column({ type: 'varchar', length: 50, nullable: true })
  @IsOptional()
  contactPerson: string;

  @Column({ type: 'varchar', length: 100, nullable: true })
  @IsOptional()
  contactEmail: string;

  @Column({ type: 'varchar', length: 20, nullable: true })
  @IsOptional()
  contactPhone: string;

  @Column({ type: 'date', nullable: true })
  @IsOptional()
  interviewDate: Date;

  @Column({ type: 'varchar', length: 50, nullable: true })
  @IsOptional()
  source: string; // LinkedIn, Indeed, Company Website, etc.

  @Column({ type: 'int', nullable: true })
  @IsOptional()
  priority: number; // 1-5 priority level

  @ManyToOne(() => User, user => user.jobs, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'userId' })
  user: User;

  @Column({ type: 'uuid' })
  userId: string;

  @CreateDateColumn()
  createdAt: Date;

  @UpdateDateColumn()
  updatedAt: Date;

  @BeforeInsert()
  async addId() {
    this.id = uuidv4();
  }

  // Helper methods
  getDaysApplied(): number {
    const today = new Date();
    const applied = new Date(this.dateApplied);
    const diffTime = Math.abs(today.getTime() - applied.getTime());
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  }

  isRecentApplication(): boolean {
    return this.getDaysApplied() <= 7;
  }

  getStatusColor(): string {
    const colors = {
      applied: '#3B82F6',      // Blue
      interviewing: '#F59E0B', // Yellow
      offered: '#10B981',      // Green
      rejected: '#EF4444',     // Red
      withdrawn: '#6B7280'     // Gray
    };
    return colors[this.status] || '#6B7280';
  }

  toSummary() {
    return {
      id: this.id,
      position: this.position,
      company: this.company,
      status: this.status,
      location: this.location,
      salary: this.salary,
      dateApplied: this.dateApplied,
      daysApplied: this.getDaysApplied(),
      isRecent: this.isRecentApplication(),
      statusColor: this.getStatusColor(),
      createdAt: this.createdAt
    };
  }
}
