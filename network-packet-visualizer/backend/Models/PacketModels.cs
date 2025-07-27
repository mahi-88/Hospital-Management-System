using System.ComponentModel.DataAnnotations;
using System.Text.Json;

namespace NetworkPacketVisualizer.Models
{
    public class PacketSimulation
    {
        public Guid Id { get; set; }
        
        [Required]
        [StringLength(10)]
        public string Protocol { get; set; } = string.Empty;
        
        [Required]
        [StringLength(45)]
        public string SourceIP { get; set; } = string.Empty;
        
        [Required]
        [StringLength(45)]
        public string DestinationIP { get; set; } = string.Empty;
        
        public int SourcePort { get; set; }
        public int DestinationPort { get; set; }
        
        public int Size { get; set; }
        
        [StringLength(100)]
        public string Checksum { get; set; } = string.Empty;
        
        [StringLength(20)]
        public string Status { get; set; } = "Generated";
        
        public DateTime Timestamp { get; set; }
        
        // JSON fields for complex data
        public string HeadersJson { get; set; } = "{}";
        public string OSILayersJson { get; set; } = "{}";
        
        // Navigation properties (not mapped to database)
        public Dictionary<string, object>? Headers
        {
            get => string.IsNullOrEmpty(HeadersJson) ? 
                new Dictionary<string, object>() : 
                JsonSerializer.Deserialize<Dictionary<string, object>>(HeadersJson);
            set => HeadersJson = JsonSerializer.Serialize(value ?? new Dictionary<string, object>());
        }
        
        public Dictionary<string, object>? OSILayers
        {
            get => string.IsNullOrEmpty(OSILayersJson) ? 
                new Dictionary<string, object>() : 
                JsonSerializer.Deserialize<Dictionary<string, object>>(OSILayersJson);
            set => OSILayersJson = JsonSerializer.Serialize(value ?? new Dictionary<string, object>());
        }
    }

    public class PacketGenerationRequest
    {
        [Required]
        public string Protocol { get; set; } = string.Empty;
        
        [Required]
        public string SourceIP { get; set; } = string.Empty;
        
        [Required]
        public string DestinationIP { get; set; } = string.Empty;
        
        public int SourcePort { get; set; }
        public int DestinationPort { get; set; }
        
        public Dictionary<string, object>? CustomHeaders { get; set; }
        public string? PayloadData { get; set; }
    }

    public class PacketAnalysis
    {
        public Guid PacketId { get; set; }
        public string Protocol { get; set; } = string.Empty;
        public bool IsValidChecksum { get; set; }
        public Dictionary<string, object> HeaderAnalysis { get; set; } = new();
        public Dictionary<string, object> OSILayerBreakdown { get; set; } = new();
        public Dictionary<string, object> SecurityAnalysis { get; set; } = new();
        public Dictionary<string, object> PerformanceMetrics { get; set; } = new();
        public DateTime AnalysisTimestamp { get; set; } = DateTime.UtcNow;
    }

    public class NetworkMetrics
    {
        public int Id { get; set; }
        public DateTime Timestamp { get; set; }
        public int TotalPackets { get; set; }
        public int TCPPackets { get; set; }
        public int UDPPackets { get; set; }
        public int ICMPPackets { get; set; }
        public int HTTPPackets { get; set; }
        public int DNSPackets { get; set; }
        public int ErrorCount { get; set; }
        public double AverageLatency { get; set; }
        public double Throughput { get; set; }
        public double PacketLossRate { get; set; }
    }

    public class SimulationLog
    {
        public int Id { get; set; }
        public Guid? PacketId { get; set; }
        public DateTime Timestamp { get; set; }
        public string LogLevel { get; set; } = string.Empty;
        public string Message { get; set; } = string.Empty;
        public string? Details { get; set; }
        
        // Navigation property
        public PacketSimulation? Packet { get; set; }
    }

    public class OSILayerInfo
    {
        public int LayerNumber { get; set; }
        public string Name { get; set; } = string.Empty;
        public string Description { get; set; } = string.Empty;
        public List<string> Functions { get; set; } = new();
        public List<string> Protocols { get; set; } = new();
        public Dictionary<string, object> ExampleData { get; set; } = new();
    }

    public class NetworkTopology
    {
        public int Id { get; set; }
        public string Name { get; set; } = string.Empty;
        public string Description { get; set; } = string.Empty;
        public string NodesJson { get; set; } = "[]";
        public string ConnectionsJson { get; set; } = "[]";
        public DateTime CreatedAt { get; set; }
        public DateTime UpdatedAt { get; set; }
        
        public List<NetworkNode>? Nodes
        {
            get => string.IsNullOrEmpty(NodesJson) ? 
                new List<NetworkNode>() : 
                JsonSerializer.Deserialize<List<NetworkNode>>(NodesJson);
            set => NodesJson = JsonSerializer.Serialize(value ?? new List<NetworkNode>());
        }
        
        public List<NetworkConnection>? Connections
        {
            get => string.IsNullOrEmpty(ConnectionsJson) ? 
                new List<NetworkConnection>() : 
                JsonSerializer.Deserialize<List<NetworkConnection>>(ConnectionsJson);
            set => ConnectionsJson = JsonSerializer.Serialize(value ?? new List<NetworkConnection>());
        }
    }

    public class NetworkNode
    {
        public string Id { get; set; } = string.Empty;
        public string Name { get; set; } = string.Empty;
        public string Type { get; set; } = string.Empty; // Router, Switch, Host, Server
        public string IPAddress { get; set; } = string.Empty;
        public string MACAddress { get; set; } = string.Empty;
        public double X { get; set; }
        public double Y { get; set; }
        public Dictionary<string, object> Properties { get; set; } = new();
    }

    public class NetworkConnection
    {
        public string Id { get; set; } = string.Empty;
        public string SourceNodeId { get; set; } = string.Empty;
        public string DestinationNodeId { get; set; } = string.Empty;
        public string ConnectionType { get; set; } = string.Empty; // Ethernet, WiFi, Fiber
        public double Bandwidth { get; set; }
        public double Latency { get; set; }
        public Dictionary<string, object> Properties { get; set; } = new();
    }

    public class PacketFlowStep
    {
        public int StepNumber { get; set; }
        public string NodeId { get; set; } = string.Empty;
        public string Action { get; set; } = string.Empty;
        public string Description { get; set; } = string.Empty;
        public DateTime Timestamp { get; set; }
        public Dictionary<string, object> Data { get; set; } = new();
    }

    public class ProtocolStatistics
    {
        public string Protocol { get; set; } = string.Empty;
        public int PacketCount { get; set; }
        public long TotalBytes { get; set; }
        public double AverageSize { get; set; }
        public double Percentage { get; set; }
        public DateTime LastSeen { get; set; }
    }

    public class ErrorLog
    {
        public int Id { get; set; }
        public Guid? PacketId { get; set; }
        public DateTime Timestamp { get; set; }
        public string ErrorType { get; set; } = string.Empty;
        public string ErrorMessage { get; set; } = string.Empty;
        public string? StackTrace { get; set; }
        public string? AdditionalData { get; set; }
        
        // Navigation property
        public PacketSimulation? Packet { get; set; }
    }

    public class PerformanceMetric
    {
        public int Id { get; set; }
        public DateTime Timestamp { get; set; }
        public string MetricName { get; set; } = string.Empty;
        public double Value { get; set; }
        public string Unit { get; set; } = string.Empty;
        public string? Category { get; set; }
        public Dictionary<string, object> Metadata { get; set; } = new();
    }
}
