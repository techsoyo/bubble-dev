/**
 * Centralized Security Logging System
 * Provides secure, structured logging for security events and violations
 */

export interface SecurityLogEntry {
  timestamp: string;
  level: 'info' | 'warn' | 'error' | 'critical';
  category: 'auth' | 'xss' | 'csrf' | 'csp' | 'input' | 'session' | 'general';
  event: string;
  userId?: string;
  ip?: string;
  userAgent?: string;
  details?: Record<string, any>;
  stackTrace?: string;
}

export interface SecurityMetrics {
  totalEvents: number;
  eventsByLevel: Record<string, number>;
  eventsByCategory: Record<string, number>;
  recentEvents: SecurityLogEntry[];
  lastUpdated: string;
}

class SecurityLogger {
  private static instance: SecurityLogger;
  private logs: SecurityLogEntry[] = [];
  private readonly maxLogs = 1000; // Keep last 1000 entries in memory
  private readonly batchSize = 10; // Send logs in batches
  private pendingLogs: SecurityLogEntry[] = [];

  private constructor() {
    // Initialize security logging
    this.initSecurityLogging();
  }

  static getInstance(): SecurityLogger {
    if (!SecurityLogger.instance) {
      SecurityLogger.instance = new SecurityLogger();
    }
    return SecurityLogger.instance;
  }

  private initSecurityLogging(): void {
    // Listen for security events
    if (typeof window !== 'undefined') {
      // CSP violation events
      document.addEventListener('securitypolicyviolation', (event) => {
        this.logSecurityEvent({
          level: 'warn',
          category: 'csp',
          event: 'CSP_VIOLATION',
          details: {
            violatedDirective: event.violatedDirective,
            blockedURI: event.blockedURI,
            sourceFile: event.sourceFile,
            lineNumber: event.lineNumber,
          }
        });
      });

      // Global error handler for security-related errors
      window.addEventListener('error', (event) => {
        if (this.isSecurityRelatedError(event.message)) {
          this.logSecurityEvent({
            level: 'error',
            category: 'general',
            event: 'JAVASCRIPT_ERROR',
            details: {
              message: event.message,
              filename: event.filename,
              lineno: event.lineno,
              colno: event.colno,
            }
          });
        }
      });

      // Unhandled promise rejections
      window.addEventListener('unhandledrejection', (event) => {
        if (this.isSecurityRelatedError(event.reason?.toString())) {
          this.logSecurityEvent({
            level: 'error',
            category: 'general',
            event: 'UNHANDLED_PROMISE_REJECTION',
            details: {
              reason: event.reason?.toString(),
            }
          });
        }
      });
    }
  }

  private isSecurityRelatedError(message: string): boolean {
    const securityKeywords = [
      'xss', 'csrf', 'injection', 'script', 'eval', 'innerHTML',
      'localStorage', 'sessionStorage', 'cookie', 'token', 'auth',
      'security', 'violation', 'attack', 'exploit'
    ];

    return securityKeywords.some(keyword =>
      message.toLowerCase().includes(keyword)
    );
  }

  /**
   * Log a security event
   */
  logSecurityEvent(entry: Omit<SecurityLogEntry, 'timestamp'>): void {
    const logEntry: SecurityLogEntry = {
      ...entry,
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent,
    };

    // Add to in-memory logs
    this.logs.push(logEntry);

    // Keep only recent logs
    if (this.logs.length > this.maxLogs) {
      this.logs = this.logs.slice(-this.maxLogs);
    }

    // Add to pending batch
    this.pendingLogs.push(logEntry);

    // Send batch if threshold reached
    if (this.pendingLogs.length >= this.batchSize) {
      this.flushLogs();
    }

    // Console logging for development
    if (import.meta.env.MODE === 'development') {
      const logMethod = entry.level === 'error' || entry.level === 'critical'
        ? 'error'
        : entry.level === 'warn'
          ? 'warn'
          : 'log';

      console[logMethod](`🔒 [${entry.category.toUpperCase()}] ${entry.event}`, entry.details || {});
    }
  }

  /**
   * Log authentication events
   */
  logAuthEvent(event: string, details?: Record<string, any>, userId?: string): void {
    this.logSecurityEvent({
      level: event.includes('failed') || event.includes('invalid') ? 'warn' : 'info',
      category: 'auth',
      event,
      userId,
      details,
    });
  }

  /**
   * Log XSS prevention events
   */
  logXSSAttempt(details: Record<string, any>): void {
    this.logSecurityEvent({
      level: 'critical',
      category: 'xss',
      event: 'XSS_ATTEMPT_BLOCKED',
      details,
    });
  }

  /**
   * Log input validation events
   */
  logInputValidation(event: string, details: Record<string, any>): void {
    this.logSecurityEvent({
      level: 'warn',
      category: 'input',
      event,
      details,
    });
  }

  /**
   * Log session events
   */
  logSessionEvent(event: string, userId?: string, details?: Record<string, any>): void {
    this.logSecurityEvent({
      level: 'info',
      category: 'session',
      event,
      userId,
      details,
    });
  }

  /**
   * Get security metrics
   */
  getSecurityMetrics(): SecurityMetrics {
    const eventsByLevel = this.logs.reduce((acc, log) => {
      acc[log.level] = (acc[log.level] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    const eventsByCategory = this.logs.reduce((acc, log) => {
      acc[log.category] = (acc[log.category] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    return {
      totalEvents: this.logs.length,
      eventsByLevel,
      eventsByCategory,
      recentEvents: this.logs.slice(-10), // Last 10 events
      lastUpdated: new Date().toISOString(),
    };
  }

  /**
   * Get logs by category
   */
  getLogsByCategory(category: SecurityLogEntry['category']): SecurityLogEntry[] {
    return this.logs.filter(log => log.category === category);
  }

  /**
   * Get logs by level
   */
  getLogsByLevel(level: SecurityLogEntry['level']): SecurityLogEntry[] {
    return this.logs.filter(log => log.level === level);
  }

  /**
   * Clear all logs
   */
  clearLogs(): void {
    this.logs = [];
    this.pendingLogs = [];
  }

  /**
   * Flush pending logs to server
   */
  private async flushLogs(): Promise<void> {
    if (this.pendingLogs.length === 0) return;

    const logsToSend = [...this.pendingLogs];
    this.pendingLogs = [];

    try {
      // Send logs to server endpoint
      const response = await fetch('/api/security-logs', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ logs: logsToSend }),
        credentials: 'include', // Include cookies for authentication
      });

      if (!response.ok) {
        // Re-queue logs if sending failed
        this.pendingLogs.unshift(...logsToSend);
        console.warn('Failed to send security logs to server');
      }
    } catch (error) {
      // Re-queue logs on error
      this.pendingLogs.unshift(...logsToSend);
      console.warn('Error sending security logs:', error);
    }
  }

  /**
   * Force flush all pending logs
   */
  async forceFlush(): Promise<void> {
    await this.flushLogs();
  }

  /**
   * Export logs for analysis
   */
  exportLogs(): string {
    return JSON.stringify(this.logs, null, 2);
  }
}

// Export singleton instance
export const securityLogger = SecurityLogger.getInstance();

// Export convenience functions
export const logAuthEvent = (event: string, details?: Record<string, any>, userId?: string) =>
  securityLogger.logAuthEvent(event, details, userId);

export const logXSSAttempt = (details: Record<string, any>) =>
  securityLogger.logXSSAttempt(details);

export const logInputValidation = (event: string, details: Record<string, any>) =>
  securityLogger.logInputValidation(event, details);

export const logSessionEvent = (event: string, userId?: string, details?: Record<string, any>) =>
  securityLogger.logSessionEvent(event, userId, details);

export default securityLogger;
