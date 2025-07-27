# Sample Documents for PixelForge Nexus

This directory contains sample documents that are referenced in the database seed data. These files demonstrate the document management functionality of the system.

## Document List

### Project 1: Mystic Quest RPG
- `mystic-quest-design-doc.pdf` - Complete game design document
- `character-concepts.png` - Character concept art and design sketches
- `sprint-planning-notes.txt` - Sprint planning meeting notes

### Project 2: Space Shooter Arcade
- `space-shooter-spec.pdf` - Technical specification document
- `ui-mockups.png` - UI mockups and wireframes

### Project 3: Puzzle Adventure
- `puzzle-game-postmortem.pdf` - Project postmortem analysis
- `final-assets.zip` - Complete package of final game assets

## File Security

All documents in this system are:
- Validated for file type and size
- Scanned for security threats
- Access-controlled based on project assignments
- Tracked in audit logs for compliance

## Usage in Development

These sample files are automatically referenced when you run the database seeding script. The actual files are not required for the system to function, but they provide realistic examples of the document management features.

## Adding New Sample Documents

To add new sample documents:

1. Place the file in this directory
2. Update the database seed script (`backend/prisma/seed.ts`)
3. Add the document metadata to the sample data
4. Run `npm run db:seed` to update the database

## File Types Supported

The system supports the following file types:
- PDF documents (`.pdf`)
- Images (`.jpg`, `.jpeg`, `.png`, `.gif`)
- Text files (`.txt`)
- Microsoft Word documents (`.doc`, `.docx`)
- Archive files (`.zip`) - for asset packages

## Security Considerations

- Maximum file size: 10MB
- File type validation on upload
- Virus scanning (in production)
- Access control based on project membership
- Download tracking and audit logging
