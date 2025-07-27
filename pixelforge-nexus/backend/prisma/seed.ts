import { PrismaClient, UserRole, ProjectStatus, DocumentType } from '@prisma/client';
import bcrypt from 'bcryptjs';

const prisma = new PrismaClient();

async function main() {
  console.log('🌱 Starting database seeding...');

  // Create default users
  const adminPassword = await bcrypt.hash('Admin123!@#', 12);
  const leadPassword = await bcrypt.hash('Lead123!@#', 12);
  const devPassword = await bcrypt.hash('Dev123!@#', 12);

  // Create Admin user
  const admin = await prisma.user.upsert({
    where: { email: 'admin@pixelforge.com' },
    update: {},
    create: {
      email: 'admin@pixelforge.com',
      passwordHash: adminPassword,
      firstName: 'Admin',
      lastName: 'User',
      role: UserRole.ADMIN,
      isActive: true,
      emailVerified: true
    }
  });

  // Create Project Lead user
  const projectLead = await prisma.user.upsert({
    where: { email: 'lead@pixelforge.com' },
    update: {},
    create: {
      email: 'lead@pixelforge.com',
      passwordHash: leadPassword,
      firstName: 'Project',
      lastName: 'Lead',
      role: UserRole.PROJECT_LEAD,
      isActive: true,
      emailVerified: true
    }
  });

  // Create Developer user
  const developer = await prisma.user.upsert({
    where: { email: 'dev@pixelforge.com' },
    update: {},
    create: {
      email: 'dev@pixelforge.com',
      passwordHash: devPassword,
      firstName: 'Developer',
      lastName: 'User',
      role: UserRole.DEVELOPER,
      isActive: true,
      emailVerified: true
    }
  });

  // Create additional test users
  const lead2Password = await bcrypt.hash('Lead456!@#', 12);
  const projectLead2 = await prisma.user.upsert({
    where: { email: 'lead2@pixelforge.com' },
    update: {},
    create: {
      email: 'lead2@pixelforge.com',
      passwordHash: lead2Password,
      firstName: 'Sarah',
      lastName: 'Johnson',
      role: UserRole.PROJECT_LEAD,
      isActive: true,
      emailVerified: true,
      mfaEnabled: true,
      mfaSecret: 'JBSWY3DPEHPK3PXP'
    }
  });

  const dev2Password = await bcrypt.hash('Dev456!@#', 12);
  const developer2 = await prisma.user.upsert({
    where: { email: 'dev2@pixelforge.com' },
    update: {},
    create: {
      email: 'dev2@pixelforge.com',
      passwordHash: dev2Password,
      firstName: 'Mike',
      lastName: 'Chen',
      role: UserRole.DEVELOPER,
      isActive: true,
      emailVerified: true
    }
  });

  const dev3Password = await bcrypt.hash('Dev789!@#', 12);
  const developer3 = await prisma.user.upsert({
    where: { email: 'dev3@pixelforge.com' },
    update: {},
    create: {
      email: 'dev3@pixelforge.com',
      passwordHash: dev3Password,
      firstName: 'Emma',
      lastName: 'Davis',
      role: UserRole.DEVELOPER,
      isActive: true,
      emailVerified: true
    }
  });

  console.log('✅ Users created successfully');

  // Create sample projects
  const project1 = await prisma.project.upsert({
    where: { id: 'project-001' },
    update: {},
    create: {
      id: 'project-001',
      name: 'Mystic Quest RPG',
      description: 'An epic fantasy role-playing game with immersive storytelling and dynamic combat system.',
      status: ProjectStatus.ACTIVE,
      deadline: new Date('2025-06-30'),
      startDate: new Date('2024-12-01'),
      creatorId: admin.id,
      leadId: projectLead.id
    }
  });

  const project2 = await prisma.project.upsert({
    where: { id: 'project-002' },
    update: {},
    create: {
      id: 'project-002',
      name: 'Space Shooter Arcade',
      description: 'Fast-paced arcade-style space shooter with retro aesthetics and modern gameplay mechanics.',
      status: ProjectStatus.ACTIVE,
      deadline: new Date('2025-04-15'),
      startDate: new Date('2025-01-01'),
      creatorId: admin.id,
      leadId: projectLead2.id
    }
  });

  const project3 = await prisma.project.upsert({
    where: { id: 'project-003' },
    update: {},
    create: {
      id: 'project-003',
      name: 'Puzzle Adventure',
      description: 'Mind-bending puzzle game with beautiful hand-drawn art and atmospheric soundtrack.',
      status: ProjectStatus.COMPLETED,
      deadline: new Date('2025-01-15'),
      startDate: new Date('2024-10-01'),
      endDate: new Date('2025-01-10'),
      creatorId: admin.id,
      leadId: admin.id
    }
  });

  console.log('✅ Projects created successfully');

  // Create project assignments
  await prisma.projectAssignment.createMany({
    data: [
      {
        userId: developer.id,
        projectId: project1.id,
        assignedBy: projectLead.id
      },
      {
        userId: developer2.id,
        projectId: project1.id,
        assignedBy: projectLead.id
      },
      {
        userId: developer3.id,
        projectId: project2.id,
        assignedBy: projectLead2.id
      },
      {
        userId: developer.id,
        projectId: project3.id,
        assignedBy: admin.id
      },
      {
        userId: developer2.id,
        projectId: project3.id,
        assignedBy: admin.id
      },
      {
        userId: developer3.id,
        projectId: project3.id,
        assignedBy: admin.id
      }
    ],
    skipDuplicates: true
  });

  console.log('✅ Project assignments created successfully');

  // Create sample documents (metadata only - no actual files)
  await prisma.document.createMany({
    data: [
      {
        id: 'doc-001',
        filename: 'mystic-quest-design-doc.pdf',
        originalName: 'Mystic Quest - Game Design Document.pdf',
        mimeType: 'application/pdf',
        size: 2048576,
        type: DocumentType.DESIGN_DOC,
        description: 'Complete game design document outlining gameplay mechanics, story, and technical requirements.',
        filePath: '/uploads/sample/mystic-quest-design-doc.pdf',
        fileHash: 'abc123def456',
        projectId: project1.id,
        uploadedById: projectLead.id
      },
      {
        id: 'doc-002',
        filename: 'character-concepts.png',
        originalName: 'Character Concept Art.png',
        mimeType: 'image/png',
        size: 1024768,
        type: DocumentType.ASSET,
        description: 'Initial character concept art and design sketches.',
        filePath: '/uploads/sample/character-concepts.png',
        fileHash: 'def456ghi789',
        projectId: project1.id,
        uploadedById: projectLead.id
      },
      {
        id: 'doc-003',
        filename: 'sprint-planning-notes.txt',
        originalName: 'Sprint Planning Meeting Notes.txt',
        mimeType: 'text/plain',
        size: 15360,
        type: DocumentType.MEETING_NOTES,
        description: 'Notes from the sprint planning meeting covering development priorities.',
        filePath: '/uploads/sample/sprint-planning-notes.txt',
        fileHash: 'ghi789jkl012',
        projectId: project1.id,
        uploadedById: projectLead.id
      },
      {
        id: 'doc-004',
        filename: 'space-shooter-spec.pdf',
        originalName: 'Space Shooter Technical Specification.pdf',
        mimeType: 'application/pdf',
        size: 1536000,
        type: DocumentType.SPECIFICATION,
        description: 'Technical specification document for the space shooter game engine.',
        filePath: '/uploads/sample/space-shooter-spec.pdf',
        fileHash: 'jkl012mno345',
        projectId: project2.id,
        uploadedById: projectLead2.id
      },
      {
        id: 'doc-005',
        filename: 'ui-mockups.png',
        originalName: 'UI Mockups and Wireframes.png',
        mimeType: 'image/png',
        size: 2048000,
        type: DocumentType.ASSET,
        description: 'User interface mockups and wireframes for the main game screens.',
        filePath: '/uploads/sample/ui-mockups.png',
        fileHash: 'mno345pqr678',
        projectId: project2.id,
        uploadedById: projectLead2.id
      },
      {
        id: 'doc-006',
        filename: 'puzzle-game-postmortem.pdf',
        originalName: 'Puzzle Adventure - Project Postmortem.pdf',
        mimeType: 'application/pdf',
        size: 1024000,
        type: DocumentType.OTHER,
        description: 'Project postmortem analysis covering what went well and lessons learned.',
        filePath: '/uploads/sample/puzzle-game-postmortem.pdf',
        fileHash: 'pqr678stu901',
        projectId: project3.id,
        uploadedById: admin.id
      },
      {
        id: 'doc-007',
        filename: 'final-assets.zip',
        originalName: 'Final Game Assets Package.zip',
        mimeType: 'application/zip',
        size: 10485760,
        type: DocumentType.ASSET,
        description: 'Complete package of final game assets including sprites, sounds, and animations.',
        filePath: '/uploads/sample/final-assets.zip',
        fileHash: 'stu901vwx234',
        projectId: project3.id,
        uploadedById: admin.id
      }
    ],
    skipDuplicates: true
  });

  console.log('✅ Sample documents created successfully');

  // Create sample audit logs
  await prisma.auditLog.createMany({
    data: [
      {
        action: 'LOGIN',
        resource: 'User',
        resourceId: admin.id,
        userId: admin.id,
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        success: true,
        createdAt: new Date(Date.now() - 2 * 60 * 60 * 1000) // 2 hours ago
      },
      {
        action: 'CREATE',
        resource: 'Project',
        resourceId: project1.id,
        userId: admin.id,
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        success: true,
        newValues: { name: 'Mystic Quest RPG' },
        createdAt: new Date(Date.now() - 24 * 60 * 60 * 1000) // 1 day ago
      },
      {
        action: 'ASSIGN',
        resource: 'Project',
        resourceId: project1.id,
        userId: projectLead.id,
        ipAddress: '192.168.1.100',
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        success: true,
        newValues: { assignedUserId: developer.id },
        createdAt: new Date(Date.now() - 20 * 60 * 60 * 1000) // 20 hours ago
      }
    ],
    skipDuplicates: true
  });

  // Create sample security events
  await prisma.securityEvent.createMany({
    data: [
      {
        eventType: 'LOGIN_SUCCESS',
        severity: 'INFO',
        description: 'User logged in successfully',
        ipAddress: '127.0.0.1',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        userId: admin.id,
        resolved: true,
        createdAt: new Date(Date.now() - 2 * 60 * 60 * 1000)
      },
      {
        eventType: 'FAILED_LOGIN_ATTEMPT',
        severity: 'WARNING',
        description: 'Failed login attempt with invalid password',
        ipAddress: '192.168.1.200',
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        metadata: { email: 'unknown@example.com' },
        resolved: false,
        createdAt: new Date(Date.now() - 6 * 60 * 60 * 1000)
      }
    ],
    skipDuplicates: true
  });

  console.log('✅ Sample audit logs and security events created successfully');
  console.log('🎉 Database seeding completed!');
  
  console.log('\n📋 Default Login Credentials:');
  console.log('Admin: admin@pixelforge.com / Admin123!@#');
  console.log('Project Lead: lead@pixelforge.com / Lead123!@#');
  console.log('Developer: dev@pixelforge.com / Dev123!@#');
  console.log('Project Lead 2 (MFA): lead2@pixelforge.com / Lead456!@#');
  console.log('Developer 2: dev2@pixelforge.com / Dev456!@#');
  console.log('Developer 3: dev3@pixelforge.com / Dev789!@#');
}

main()
  .catch((e) => {
    console.error('❌ Error during seeding:', e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
