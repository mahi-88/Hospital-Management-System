-- Network Packet Visualizer Database Schema
-- SQL Server Database Schema

-- Create Database
IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'NetworkPacketVisualizer')
BEGIN
    CREATE DATABASE NetworkPacketVisualizer;
END
GO

USE NetworkPacketVisualizer;
GO

-- Drop existing tables if they exist (for development)
IF OBJECT_ID('dbo.PerformanceMetrics', 'U') IS NOT NULL DROP TABLE dbo.PerformanceMetrics;
IF OBJECT_ID('dbo.ErrorLogs', 'U') IS NOT NULL DROP TABLE dbo.ErrorLogs;
IF OBJECT_ID('dbo.SimulationLogs', 'U') IS NOT NULL DROP TABLE dbo.SimulationLogs;
IF OBJECT_ID('dbo.NetworkTopologies', 'U') IS NOT NULL DROP TABLE dbo.NetworkTopologies;
IF OBJECT_ID('dbo.NetworkMetrics', 'U') IS NOT NULL DROP TABLE dbo.NetworkMetrics;
IF OBJECT_ID('dbo.PacketSimulations', 'U') IS NOT NULL DROP TABLE dbo.PacketSimulations;
GO

-- Create PacketSimulations table
CREATE TABLE PacketSimulations (
    Id UNIQUEIDENTIFIER PRIMARY KEY DEFAULT NEWID(),
    Protocol NVARCHAR(10) NOT NULL,
    SourceIP NVARCHAR(45) NOT NULL,
    DestinationIP NVARCHAR(45) NOT NULL,
    SourcePort INT NOT NULL DEFAULT 0,
    DestinationPort INT NOT NULL DEFAULT 0,
    Size INT NOT NULL DEFAULT 0,
    Checksum NVARCHAR(100) NULL,
    Status NVARCHAR(20) NOT NULL DEFAULT 'Generated',
    Timestamp DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    HeadersJson NVARCHAR(MAX) NOT NULL DEFAULT '{}',
    OSILayersJson NVARCHAR(MAX) NOT NULL DEFAULT '{}'
);
GO

-- Create indexes for PacketSimulations
CREATE INDEX IX_PacketSimulations_Protocol ON PacketSimulations(Protocol);
CREATE INDEX IX_PacketSimulations_Timestamp ON PacketSimulations(Timestamp);
CREATE INDEX IX_PacketSimulations_SourceIP_DestinationIP ON PacketSimulations(SourceIP, DestinationIP);
CREATE INDEX IX_PacketSimulations_Status ON PacketSimulations(Status);
GO

-- Create NetworkMetrics table
CREATE TABLE NetworkMetrics (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Timestamp DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    TotalPackets INT NOT NULL DEFAULT 0,
    TCPPackets INT NOT NULL DEFAULT 0,
    UDPPackets INT NOT NULL DEFAULT 0,
    ICMPPackets INT NOT NULL DEFAULT 0,
    HTTPPackets INT NOT NULL DEFAULT 0,
    DNSPackets INT NOT NULL DEFAULT 0,
    ErrorCount INT NOT NULL DEFAULT 0,
    AverageLatency FLOAT NOT NULL DEFAULT 0.0,
    Throughput FLOAT NOT NULL DEFAULT 0.0,
    PacketLossRate FLOAT NOT NULL DEFAULT 0.0
);
GO

-- Create index for NetworkMetrics
CREATE INDEX IX_NetworkMetrics_Timestamp ON NetworkMetrics(Timestamp);
GO

-- Create SimulationLogs table
CREATE TABLE SimulationLogs (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    PacketId UNIQUEIDENTIFIER NULL,
    Timestamp DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    LogLevel NVARCHAR(20) NOT NULL,
    Message NVARCHAR(1000) NOT NULL,
    Details NVARCHAR(MAX) NULL,
    FOREIGN KEY (PacketId) REFERENCES PacketSimulations(Id) ON DELETE SET NULL
);
GO

-- Create indexes for SimulationLogs
CREATE INDEX IX_SimulationLogs_Timestamp ON SimulationLogs(Timestamp);
CREATE INDEX IX_SimulationLogs_LogLevel ON SimulationLogs(LogLevel);
CREATE INDEX IX_SimulationLogs_PacketId ON SimulationLogs(PacketId);
GO

-- Create NetworkTopologies table
CREATE TABLE NetworkTopologies (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Name NVARCHAR(100) NOT NULL,
    Description NVARCHAR(500) NULL,
    NodesJson NVARCHAR(MAX) NOT NULL DEFAULT '[]',
    ConnectionsJson NVARCHAR(MAX) NOT NULL DEFAULT '[]',
    CreatedAt DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    UpdatedAt DATETIME2 NOT NULL DEFAULT GETUTCDATE()
);
GO

-- Create index for NetworkTopologies
CREATE INDEX IX_NetworkTopologies_Name ON NetworkTopologies(Name);
GO

-- Create ErrorLogs table
CREATE TABLE ErrorLogs (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    PacketId UNIQUEIDENTIFIER NULL,
    Timestamp DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    ErrorType NVARCHAR(100) NOT NULL,
    ErrorMessage NVARCHAR(1000) NOT NULL,
    StackTrace NVARCHAR(MAX) NULL,
    AdditionalData NVARCHAR(MAX) NULL,
    FOREIGN KEY (PacketId) REFERENCES PacketSimulations(Id) ON DELETE SET NULL
);
GO

-- Create indexes for ErrorLogs
CREATE INDEX IX_ErrorLogs_Timestamp ON ErrorLogs(Timestamp);
CREATE INDEX IX_ErrorLogs_ErrorType ON ErrorLogs(ErrorType);
CREATE INDEX IX_ErrorLogs_PacketId ON ErrorLogs(PacketId);
GO

-- Create PerformanceMetrics table
CREATE TABLE PerformanceMetrics (
    Id INT IDENTITY(1,1) PRIMARY KEY,
    Timestamp DATETIME2 NOT NULL DEFAULT GETUTCDATE(),
    MetricName NVARCHAR(100) NOT NULL,
    Value FLOAT NOT NULL,
    Unit NVARCHAR(20) NOT NULL,
    Category NVARCHAR(50) NULL
);
GO

-- Create indexes for PerformanceMetrics
CREATE INDEX IX_PerformanceMetrics_Timestamp ON PerformanceMetrics(Timestamp);
CREATE INDEX IX_PerformanceMetrics_MetricName ON PerformanceMetrics(MetricName);
CREATE INDEX IX_PerformanceMetrics_Category ON PerformanceMetrics(Category);
GO

-- Create views for common queries
CREATE VIEW vw_PacketStatistics AS
SELECT 
    Protocol,
    COUNT(*) as PacketCount,
    AVG(Size) as AverageSize,
    MIN(Timestamp) as FirstSeen,
    MAX(Timestamp) as LastSeen,
    COUNT(CASE WHEN Status = 'Error' THEN 1 END) as ErrorCount
FROM PacketSimulations
GROUP BY Protocol;
GO

CREATE VIEW vw_RecentActivity AS
SELECT TOP 100
    ps.Id,
    ps.Protocol,
    ps.SourceIP,
    ps.DestinationIP,
    ps.Size,
    ps.Status,
    ps.Timestamp,
    sl.LogLevel,
    sl.Message
FROM PacketSimulations ps
LEFT JOIN SimulationLogs sl ON ps.Id = sl.PacketId
ORDER BY ps.Timestamp DESC;
GO

-- Create stored procedures for common operations
CREATE PROCEDURE sp_GetPacketsByTimeRange
    @StartTime DATETIME2,
    @EndTime DATETIME2,
    @Protocol NVARCHAR(10) = NULL
AS
BEGIN
    SELECT *
    FROM PacketSimulations
    WHERE Timestamp BETWEEN @StartTime AND @EndTime
    AND (@Protocol IS NULL OR Protocol = @Protocol)
    ORDER BY Timestamp DESC;
END
GO

CREATE PROCEDURE sp_GetNetworkMetricsSummary
    @Hours INT = 24
AS
BEGIN
    DECLARE @StartTime DATETIME2 = DATEADD(HOUR, -@Hours, GETUTCDATE());
    
    SELECT 
        COUNT(*) as TotalPackets,
        COUNT(CASE WHEN Protocol = 'TCP' THEN 1 END) as TCPPackets,
        COUNT(CASE WHEN Protocol = 'UDP' THEN 1 END) as UDPPackets,
        COUNT(CASE WHEN Protocol = 'ICMP' THEN 1 END) as ICMPPackets,
        COUNT(CASE WHEN Protocol = 'HTTP' THEN 1 END) as HTTPPackets,
        COUNT(CASE WHEN Protocol = 'DNS' THEN 1 END) as DNSPackets,
        COUNT(CASE WHEN Status = 'Error' THEN 1 END) as ErrorCount,
        AVG(CAST(Size AS FLOAT)) as AveragePacketSize,
        MIN(Timestamp) as FirstPacket,
        MAX(Timestamp) as LastPacket
    FROM PacketSimulations
    WHERE Timestamp >= @StartTime;
END
GO

-- Insert sample data for testing
INSERT INTO NetworkTopologies (Name, Description, NodesJson, ConnectionsJson)
VALUES 
('Default Network', 'Basic network topology for testing', 
'[{"id":"host1","name":"Host 1","type":"Host","ipAddress":"192.168.1.100","x":100,"y":150},{"id":"router1","name":"Router 1","type":"Router","ipAddress":"192.168.1.1","x":400,"y":150},{"id":"host2","name":"Host 2","type":"Host","ipAddress":"192.168.1.200","x":700,"y":150}]',
'[{"id":"conn1","sourceNodeId":"host1","destinationNodeId":"router1","connectionType":"Ethernet","bandwidth":1000},{"id":"conn2","sourceNodeId":"router1","destinationNodeId":"host2","connectionType":"Ethernet","bandwidth":1000}]');

-- Insert initial performance metrics
INSERT INTO PerformanceMetrics (MetricName, Value, Unit, Category)
VALUES 
('CPU Usage', 15.5, '%', 'System'),
('Memory Usage', 45.2, '%', 'System'),
('Network Throughput', 125.7, 'Mbps', 'Network'),
('Packet Processing Rate', 1500, 'packets/sec', 'Network');

PRINT 'Database schema created successfully!';
PRINT 'Tables created: PacketSimulations, NetworkMetrics, SimulationLogs, NetworkTopologies, ErrorLogs, PerformanceMetrics';
PRINT 'Views created: vw_PacketStatistics, vw_RecentActivity';
PRINT 'Stored procedures created: sp_GetPacketsByTimeRange, sp_GetNetworkMetricsSummary';
GO
