using Microsoft.EntityFrameworkCore;
using NetworkPacketVisualizer.Models;

namespace NetworkPacketVisualizer.Data
{
    public class NetworkVisualizerContext : DbContext
    {
        public NetworkVisualizerContext(DbContextOptions<NetworkVisualizerContext> options)
            : base(options)
        {
        }

        // DbSets for all entities
        public DbSet<PacketSimulation> PacketSimulations { get; set; }
        public DbSet<NetworkMetrics> NetworkMetrics { get; set; }
        public DbSet<SimulationLog> SimulationLogs { get; set; }
        public DbSet<NetworkTopology> NetworkTopologies { get; set; }
        public DbSet<ErrorLog> ErrorLogs { get; set; }
        public DbSet<PerformanceMetric> PerformanceMetrics { get; set; }

        protected override void OnModelCreating(ModelBuilder modelBuilder)
        {
            base.OnModelCreating(modelBuilder);

            // Configure PacketSimulation entity
            modelBuilder.Entity<PacketSimulation>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.Protocol)
                    .IsRequired()
                    .HasMaxLength(10);
                
                entity.Property(e => e.SourceIP)
                    .IsRequired()
                    .HasMaxLength(45);
                
                entity.Property(e => e.DestinationIP)
                    .IsRequired()
                    .HasMaxLength(45);
                
                entity.Property(e => e.Checksum)
                    .HasMaxLength(100);
                
                entity.Property(e => e.Status)
                    .HasMaxLength(20)
                    .HasDefaultValue("Generated");
                
                entity.Property(e => e.Timestamp)
                    .HasDefaultValueSql("GETUTCDATE()");
                
                entity.Property(e => e.HeadersJson)
                    .HasColumnType("nvarchar(max)")
                    .HasDefaultValue("{}");
                
                entity.Property(e => e.OSILayersJson)
                    .HasColumnType("nvarchar(max)")
                    .HasDefaultValue("{}");

                // Indexes for performance
                entity.HasIndex(e => e.Protocol);
                entity.HasIndex(e => e.Timestamp);
                entity.HasIndex(e => new { e.SourceIP, e.DestinationIP });
            });

            // Configure NetworkMetrics entity
            modelBuilder.Entity<NetworkMetrics>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.Timestamp)
                    .HasDefaultValueSql("GETUTCDATE()");
                
                entity.HasIndex(e => e.Timestamp);
            });

            // Configure SimulationLog entity
            modelBuilder.Entity<SimulationLog>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.LogLevel)
                    .IsRequired()
                    .HasMaxLength(20);
                
                entity.Property(e => e.Message)
                    .IsRequired()
                    .HasMaxLength(1000);
                
                entity.Property(e => e.Details)
                    .HasColumnType("nvarchar(max)");
                
                entity.Property(e => e.Timestamp)
                    .HasDefaultValueSql("GETUTCDATE()");

                // Foreign key relationship
                entity.HasOne(e => e.Packet)
                    .WithMany()
                    .HasForeignKey(e => e.PacketId)
                    .OnDelete(DeleteBehavior.SetNull);

                // Indexes
                entity.HasIndex(e => e.Timestamp);
                entity.HasIndex(e => e.LogLevel);
                entity.HasIndex(e => e.PacketId);
            });

            // Configure NetworkTopology entity
            modelBuilder.Entity<NetworkTopology>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.Name)
                    .IsRequired()
                    .HasMaxLength(100);
                
                entity.Property(e => e.Description)
                    .HasMaxLength(500);
                
                entity.Property(e => e.NodesJson)
                    .HasColumnType("nvarchar(max)")
                    .HasDefaultValue("[]");
                
                entity.Property(e => e.ConnectionsJson)
                    .HasColumnType("nvarchar(max)")
                    .HasDefaultValue("[]");
                
                entity.Property(e => e.CreatedAt)
                    .HasDefaultValueSql("GETUTCDATE()");
                
                entity.Property(e => e.UpdatedAt)
                    .HasDefaultValueSql("GETUTCDATE()");

                // Index
                entity.HasIndex(e => e.Name);
            });

            // Configure ErrorLog entity
            modelBuilder.Entity<ErrorLog>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.ErrorType)
                    .IsRequired()
                    .HasMaxLength(100);
                
                entity.Property(e => e.ErrorMessage)
                    .IsRequired()
                    .HasMaxLength(1000);
                
                entity.Property(e => e.StackTrace)
                    .HasColumnType("nvarchar(max)");
                
                entity.Property(e => e.AdditionalData)
                    .HasColumnType("nvarchar(max)");
                
                entity.Property(e => e.Timestamp)
                    .HasDefaultValueSql("GETUTCDATE()");

                // Foreign key relationship
                entity.HasOne(e => e.Packet)
                    .WithMany()
                    .HasForeignKey(e => e.PacketId)
                    .OnDelete(DeleteBehavior.SetNull);

                // Indexes
                entity.HasIndex(e => e.Timestamp);
                entity.HasIndex(e => e.ErrorType);
                entity.HasIndex(e => e.PacketId);
            });

            // Configure PerformanceMetric entity
            modelBuilder.Entity<PerformanceMetric>(entity =>
            {
                entity.HasKey(e => e.Id);
                entity.Property(e => e.Id).ValueGeneratedOnAdd();
                
                entity.Property(e => e.MetricName)
                    .IsRequired()
                    .HasMaxLength(100);
                
                entity.Property(e => e.Unit)
                    .IsRequired()
                    .HasMaxLength(20);
                
                entity.Property(e => e.Category)
                    .HasMaxLength(50);
                
                entity.Property(e => e.Timestamp)
                    .HasDefaultValueSql("GETUTCDATE()");

                // Indexes
                entity.HasIndex(e => e.Timestamp);
                entity.HasIndex(e => e.MetricName);
                entity.HasIndex(e => e.Category);
            });

            // Seed data for OSI Layer information
            SeedOSILayerData(modelBuilder);
        }

        private void SeedOSILayerData(ModelBuilder modelBuilder)
        {
            // This would typically be in a separate seeding class
            // For now, we'll handle this in the service layer
        }

        public override async Task<int> SaveChangesAsync(CancellationToken cancellationToken = default)
        {
            // Update timestamps for entities that have UpdatedAt property
            var entries = ChangeTracker.Entries()
                .Where(e => e.Entity is NetworkTopology && e.State == EntityState.Modified);

            foreach (var entry in entries)
            {
                if (entry.Entity is NetworkTopology topology)
                {
                    topology.UpdatedAt = DateTime.UtcNow;
                }
            }

            return await base.SaveChangesAsync(cancellationToken);
        }
    }
}
