interface SecurityEvent {
  eventType: string;
  severity: 'INFO' | 'WARNING' | 'HIGH' | 'CRITICAL';
  description: string;
  metadata?: any;
}

class SecurityService {
  private events: SecurityEvent[] = [];

  logSecurityEvent(event: SecurityEvent): void {
    const timestamp = new Date().toISOString();
    const eventWithTimestamp = {
      ...event,
      timestamp,
      userAgent: navigator.userAgent,
      url: window.location.href,
    };

    this.events.push(eventWithTimestamp);

    // Log to console in development
    if (process.env.NODE_ENV === 'development') {
      console.log('Security Event:', eventWithTimestamp);
    }

    // In production, you might want to send this to a logging service
    // this.sendToLoggingService(eventWithTimestamp);
  }

  getSecurityEvents(): SecurityEvent[] {
    return [...this.events];
  }

  clearSecurityEvents(): void {
    this.events = [];
  }

  // Content Security Policy violation handler
  handleCSPViolation(event: SecurityPolicyViolationEvent): void {
    this.logSecurityEvent({
      eventType: 'CSP_VIOLATION',
      severity: 'HIGH',
      description: `Content Security Policy violation: ${event.violatedDirective}`,
      metadata: {
        blockedURI: event.blockedURI,
        violatedDirective: event.violatedDirective,
        originalPolicy: event.originalPolicy,
      },
    });
  }

  // Detect potential XSS attempts
  detectXSSAttempt(input: string): boolean {
    const xssPatterns = [
      /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
      /javascript:/gi,
      /on\w+\s*=/gi,
      /<iframe/gi,
      /<object/gi,
      /<embed/gi,
    ];

    const hasXSS = xssPatterns.some(pattern => pattern.test(input));
    
    if (hasXSS) {
      this.logSecurityEvent({
        eventType: 'XSS_ATTEMPT',
        severity: 'HIGH',
        description: 'Potential XSS attempt detected in user input',
        metadata: { input: input.substring(0, 100) }, // Log first 100 chars only
      });
    }

    return hasXSS;
  }

  // Monitor for suspicious activity
  monitorSuspiciousActivity(): void {
    // Monitor for rapid form submissions
    let formSubmissionCount = 0;
    let lastSubmissionTime = 0;

    document.addEventListener('submit', () => {
      const now = Date.now();
      if (now - lastSubmissionTime < 1000) { // Less than 1 second between submissions
        formSubmissionCount++;
        if (formSubmissionCount > 3) {
          this.logSecurityEvent({
            eventType: 'RAPID_FORM_SUBMISSION',
            severity: 'WARNING',
            description: 'Rapid form submissions detected - possible bot activity',
            metadata: { count: formSubmissionCount },
          });
        }
      } else {
        formSubmissionCount = 0;
      }
      lastSubmissionTime = now;
    });

    // Monitor for console access (potential developer tools usage)
    let devToolsOpen = false;
    setInterval(() => {
      const threshold = 160;
      if (window.outerHeight - window.innerHeight > threshold || 
          window.outerWidth - window.innerWidth > threshold) {
        if (!devToolsOpen) {
          devToolsOpen = true;
          this.logSecurityEvent({
            eventType: 'DEVTOOLS_OPENED',
            severity: 'INFO',
            description: 'Developer tools opened',
          });
        }
      } else {
        devToolsOpen = false;
      }
    }, 1000);
  }

  // Initialize security monitoring
  initialize(): void {
    // Set up CSP violation reporting
    document.addEventListener('securitypolicyviolation', (event) => {
      this.handleCSPViolation(event);
    });

    // Start monitoring suspicious activity
    this.monitorSuspiciousActivity();

    // Log initialization
    this.logSecurityEvent({
      eventType: 'SECURITY_MONITORING_INITIALIZED',
      severity: 'INFO',
      description: 'Security monitoring system initialized',
    });
  }
}

export const securityService = new SecurityService();
