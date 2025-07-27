using NetworkPacketVisualizer.Models;
using NetworkPacketVisualizer.Data;
using Microsoft.EntityFrameworkCore;
using System.Net;
using System.Security.Cryptography;
using System.Text;

namespace NetworkPacketVisualizer.Services
{
    public interface IPacketService
    {
        Task<PacketSimulation> GeneratePacketAsync(PacketGenerationRequest request);
        Task<List<PacketSimulation>> GetPacketHistoryAsync(int limit = 100);
        Task<PacketAnalysis> AnalyzePacketAsync(int packetId);
        Task<bool> ValidateChecksumAsync(PacketSimulation packet);
    }

    public class PacketService : IPacketService
    {
        private readonly NetworkVisualizerContext _context;
        private readonly ILogger<PacketService> _logger;

        public PacketService(NetworkVisualizerContext context, ILogger<PacketService> logger)
        {
            _context = context;
            _logger = logger;
        }

        public async Task<PacketSimulation> GeneratePacketAsync(PacketGenerationRequest request)
        {
            try
            {
                _logger.LogInformation($"Generating {request.Protocol} packet from {request.SourceIP} to {request.DestinationIP}");

                var packet = new PacketSimulation
                {
                    Id = Guid.NewGuid(),
                    Protocol = request.Protocol,
                    SourceIP = request.SourceIP,
                    DestinationIP = request.DestinationIP,
                    SourcePort = request.SourcePort,
                    DestinationPort = request.DestinationPort,
                    Timestamp = DateTime.UtcNow,
                    Status = "Generated"
                };

                // Generate OSI Layer data
                packet.OSILayers = GenerateOSILayers(request);

                // Generate headers based on protocol
                switch (request.Protocol.ToUpper())
                {
                    case "TCP":
                        packet.Headers = GenerateTCPHeaders(request);
                        break;
                    case "UDP":
                        packet.Headers = GenerateUDPHeaders(request);
                        break;
                    case "ICMP":
                        packet.Headers = GenerateICMPHeaders(request);
                        break;
                    case "HTTP":
                        packet.Headers = GenerateHTTPHeaders(request);
                        break;
                    case "DNS":
                        packet.Headers = GenerateDNSHeaders(request);
                        break;
                    default:
                        throw new ArgumentException($"Unsupported protocol: {request.Protocol}");
                }

                // Calculate packet size
                packet.Size = CalculatePacketSize(packet);

                // Generate checksum
                packet.Checksum = GenerateChecksum(packet);

                // Save to database
                _context.PacketSimulations.Add(packet);
                await _context.SaveChangesAsync();

                _logger.LogInformation($"Packet generated successfully with ID: {packet.Id}");
                return packet;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error generating packet");
                throw;
            }
        }

        public async Task<List<PacketSimulation>> GetPacketHistoryAsync(int limit = 100)
        {
            return await _context.PacketSimulations
                .OrderByDescending(p => p.Timestamp)
                .Take(limit)
                .ToListAsync();
        }

        public async Task<PacketAnalysis> AnalyzePacketAsync(int packetId)
        {
            var packet = await _context.PacketSimulations.FindAsync(packetId);
            if (packet == null)
                throw new ArgumentException("Packet not found");

            var analysis = new PacketAnalysis
            {
                PacketId = packet.Id,
                Protocol = packet.Protocol,
                IsValidChecksum = await ValidateChecksumAsync(packet),
                HeaderAnalysis = AnalyzeHeaders(packet),
                OSILayerBreakdown = AnalyzeOSILayers(packet),
                SecurityAnalysis = PerformSecurityAnalysis(packet),
                PerformanceMetrics = CalculatePerformanceMetrics(packet)
            };

            return analysis;
        }

        public async Task<bool> ValidateChecksumAsync(PacketSimulation packet)
        {
            var calculatedChecksum = GenerateChecksum(packet);
            return calculatedChecksum == packet.Checksum;
        }

        private Dictionary<string, object> GenerateOSILayers(PacketGenerationRequest request)
        {
            return new Dictionary<string, object>
            {
                ["Layer1"] = new
                {
                    Name = "Physical Layer",
                    Encoding = "Manchester",
                    Medium = "Ethernet Cable",
                    SignalType = "Electrical"
                },
                ["Layer2"] = new
                {
                    Name = "Data Link Layer",
                    DestMac = GenerateRandomMac(),
                    SrcMac = GenerateRandomMac(),
                    EtherType = "0x0800",
                    FrameSize = 1518
                },
                ["Layer3"] = new
                {
                    Name = "Network Layer",
                    Version = 4,
                    HeaderLength = 20,
                    TypeOfService = 0,
                    TotalLength = 0,
                    Identification = new Random().Next(1, 65535),
                    Flags = "010",
                    FragmentOffset = 0,
                    TTL = 64,
                    Protocol = GetProtocolNumber(request.Protocol),
                    HeaderChecksum = 0,
                    SourceIP = request.SourceIP,
                    DestinationIP = request.DestinationIP
                },
                ["Layer4"] = GenerateLayer4Data(request),
                ["Layer5"] = new
                {
                    Name = "Session Layer",
                    SessionId = Guid.NewGuid().ToString(),
                    SessionState = "Established"
                },
                ["Layer6"] = new
                {
                    Name = "Presentation Layer",
                    Encryption = "None",
                    Compression = "None",
                    DataFormat = "ASCII"
                },
                ["Layer7"] = GenerateLayer7Data(request)
            };
        }

        private object GenerateLayer4Data(PacketGenerationRequest request)
        {
            switch (request.Protocol.ToUpper())
            {
                case "TCP":
                    return new
                    {
                        Name = "Transport Layer - TCP",
                        SourcePort = request.SourcePort,
                        DestinationPort = request.DestinationPort,
                        SequenceNumber = new Random().Next(),
                        AcknowledgmentNumber = 0,
                        HeaderLength = 20,
                        Flags = new
                        {
                            URG = false,
                            ACK = false,
                            PSH = false,
                            RST = false,
                            SYN = true,
                            FIN = false
                        },
                        WindowSize = 65535,
                        Checksum = 0,
                        UrgentPointer = 0
                    };
                case "UDP":
                    return new
                    {
                        Name = "Transport Layer - UDP",
                        SourcePort = request.SourcePort,
                        DestinationPort = request.DestinationPort,
                        Length = 8,
                        Checksum = 0
                    };
                default:
                    return new { Name = "Transport Layer", Protocol = request.Protocol };
            }
        }

        private object GenerateLayer7Data(PacketGenerationRequest request)
        {
            switch (request.Protocol.ToUpper())
            {
                case "HTTP":
                    return new
                    {
                        Name = "Application Layer - HTTP",
                        Method = "GET",
                        URI = "/",
                        Version = "HTTP/1.1",
                        Headers = new Dictionary<string, string>
                        {
                            ["Host"] = request.DestinationIP,
                            ["User-Agent"] = "NetworkVisualizer/1.0",
                            ["Accept"] = "text/html,application/xhtml+xml"
                        }
                    };
                case "DNS":
                    return new
                    {
                        Name = "Application Layer - DNS",
                        TransactionId = new Random().Next(1, 65535),
                        Flags = new
                        {
                            QR = false,
                            Opcode = 0,
                            AA = false,
                            TC = false,
                            RD = true,
                            RA = false,
                            ResponseCode = 0
                        },
                        Questions = 1,
                        AnswerRRs = 0,
                        AuthorityRRs = 0,
                        AdditionalRRs = 0,
                        QueryName = "example.com",
                        QueryType = "A",
                        QueryClass = "IN"
                    };
                default:
                    return new
                    {
                        Name = "Application Layer",
                        Protocol = request.Protocol,
                        Data = "Sample application data"
                    };
            }
        }

        private Dictionary<string, object> GenerateTCPHeaders(PacketGenerationRequest request)
        {
            return new Dictionary<string, object>
            {
                ["Ethernet"] = new
                {
                    DestMac = GenerateRandomMac(),
                    SrcMac = GenerateRandomMac(),
                    EtherType = "0x0800"
                },
                ["IP"] = new
                {
                    Version = 4,
                    HeaderLength = 20,
                    TotalLength = 60,
                    TTL = 64,
                    Protocol = 6,
                    SourceIP = request.SourceIP,
                    DestinationIP = request.DestinationIP,
                    Checksum = GenerateRandomChecksum()
                },
                ["TCP"] = new
                {
                    SourcePort = request.SourcePort,
                    DestinationPort = request.DestinationPort,
                    SequenceNumber = new Random().Next(),
                    AcknowledgmentNumber = 0,
                    HeaderLength = 20,
                    Flags = "SYN",
                    WindowSize = 65535,
                    Checksum = GenerateRandomChecksum()
                }
            };
        }

        private Dictionary<string, object> GenerateUDPHeaders(PacketGenerationRequest request)
        {
            return new Dictionary<string, object>
            {
                ["Ethernet"] = new
                {
                    DestMac = GenerateRandomMac(),
                    SrcMac = GenerateRandomMac(),
                    EtherType = "0x0800"
                },
                ["IP"] = new
                {
                    Version = 4,
                    HeaderLength = 20,
                    TotalLength = 28,
                    TTL = 64,
                    Protocol = 17,
                    SourceIP = request.SourceIP,
                    DestinationIP = request.DestinationIP,
                    Checksum = GenerateRandomChecksum()
                },
                ["UDP"] = new
                {
                    SourcePort = request.SourcePort,
                    DestinationPort = request.DestinationPort,
                    Length = 8,
                    Checksum = GenerateRandomChecksum()
                }
            };
        }

        private Dictionary<string, object> GenerateICMPHeaders(PacketGenerationRequest request)
        {
            return new Dictionary<string, object>
            {
                ["Ethernet"] = new
                {
                    DestMac = GenerateRandomMac(),
                    SrcMac = GenerateRandomMac(),
                    EtherType = "0x0800"
                },
                ["IP"] = new
                {
                    Version = 4,
                    HeaderLength = 20,
                    TotalLength = 28,
                    TTL = 64,
                    Protocol = 1,
                    SourceIP = request.SourceIP,
                    DestinationIP = request.DestinationIP,
                    Checksum = GenerateRandomChecksum()
                },
                ["ICMP"] = new
                {
                    Type = 8,
                    Code = 0,
                    Checksum = GenerateRandomChecksum(),
                    Identifier = new Random().Next(1, 65535),
                    SequenceNumber = 1
                }
            };
        }

        private Dictionary<string, object> GenerateHTTPHeaders(PacketGenerationRequest request)
        {
            var tcpHeaders = GenerateTCPHeaders(request);
            tcpHeaders["HTTP"] = new
            {
                Method = "GET",
                URI = "/",
                Version = "HTTP/1.1",
                Host = request.DestinationIP,
                UserAgent = "NetworkVisualizer/1.0"
            };
            return tcpHeaders;
        }

        private Dictionary<string, object> GenerateDNSHeaders(PacketGenerationRequest request)
        {
            var udpHeaders = GenerateUDPHeaders(request);
            udpHeaders["DNS"] = new
            {
                TransactionId = new Random().Next(1, 65535),
                Flags = 0x0100,
                Questions = 1,
                AnswerRRs = 0,
                AuthorityRRs = 0,
                AdditionalRRs = 0,
                QueryName = "example.com",
                QueryType = 1,
                QueryClass = 1
            };
            return udpHeaders;
        }

        private string GenerateRandomMac()
        {
            var random = new Random();
            var mac = new byte[6];
            random.NextBytes(mac);
            return string.Join(":", mac.Select(b => b.ToString("X2")));
        }

        private string GenerateRandomChecksum()
        {
            return "0x" + new Random().Next(0, 65535).ToString("X4");
        }

        private int GetProtocolNumber(string protocol)
        {
            return protocol.ToUpper() switch
            {
                "ICMP" => 1,
                "TCP" => 6,
                "UDP" => 17,
                "HTTP" => 6,
                "DNS" => 17,
                _ => 0
            };
        }

        private int CalculatePacketSize(PacketSimulation packet)
        {
            return packet.Protocol.ToUpper() switch
            {
                "TCP" => 60,
                "UDP" => 28,
                "ICMP" => 28,
                "HTTP" => 200,
                "DNS" => 50,
                _ => 64
            };
        }

        private string GenerateChecksum(PacketSimulation packet)
        {
            var data = $"{packet.SourceIP}{packet.DestinationIP}{packet.Protocol}{packet.Size}";
            using var sha256 = SHA256.Create();
            var hash = sha256.ComputeHash(Encoding.UTF8.GetBytes(data));
            return Convert.ToHexString(hash)[..8];
        }

        private Dictionary<string, object> AnalyzeHeaders(PacketSimulation packet)
        {
            return new Dictionary<string, object>
            {
                ["HeaderCount"] = packet.Headers?.Count ?? 0,
                ["TotalHeaderSize"] = 40,
                ["PayloadSize"] = packet.Size - 40,
                ["Efficiency"] = Math.Round((double)(packet.Size - 40) / packet.Size * 100, 2)
            };
        }

        private Dictionary<string, object> AnalyzeOSILayers(PacketSimulation packet)
        {
            return new Dictionary<string, object>
            {
                ["LayersProcessed"] = 7,
                ["ProcessingTime"] = "0.5ms",
                ["LayerEfficiency"] = new Dictionary<string, double>
                {
                    ["Physical"] = 100.0,
                    ["DataLink"] = 98.5,
                    ["Network"] = 97.2,
                    ["Transport"] = 96.8,
                    ["Session"] = 95.5,
                    ["Presentation"] = 94.2,
                    ["Application"] = 93.8
                }
            };
        }

        private Dictionary<string, object> PerformSecurityAnalysis(PacketSimulation packet)
        {
            return new Dictionary<string, object>
            {
                ["ThreatLevel"] = "Low",
                ["EncryptionStatus"] = "None",
                ["SuspiciousPatterns"] = false,
                ["RecommendedActions"] = new List<string>
                {
                    "Consider enabling encryption",
                    "Monitor for unusual traffic patterns"
                }
            };
        }

        private Dictionary<string, object> CalculatePerformanceMetrics(PacketSimulation packet)
        {
            return new Dictionary<string, object>
            {
                ["Latency"] = $"{new Random().Next(1, 50)}ms",
                ["Throughput"] = $"{new Random().Next(100, 1000)}Mbps",
                ["PacketLoss"] = "0%",
                ["Jitter"] = $"{new Random().Next(1, 10)}ms"
            };
        }
    }
}
