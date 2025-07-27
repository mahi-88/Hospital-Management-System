import React, { createContext, useContext, useEffect } from 'react';
import { securityService } from '../services/securityService';

interface SecurityContextType {
  logSecurityEvent: (event: any) => void;
  detectXSSAttempt: (input: string) => boolean;
}

const SecurityContext = createContext<SecurityContextType | undefined>(undefined);

export const useSecurityContext = () => {
  const context = useContext(SecurityContext);
  if (context === undefined) {
    throw new Error('useSecurityContext must be used within a SecurityProvider');
  }
  return context;
};

interface SecurityProviderProps {
  children: React.ReactNode;
}

export const SecurityProvider: React.FC<SecurityProviderProps> = ({ children }) => {
  useEffect(() => {
    // Initialize security monitoring
    securityService.initialize();
  }, []);

  const value: SecurityContextType = {
    logSecurityEvent: securityService.logSecurityEvent.bind(securityService),
    detectXSSAttempt: securityService.detectXSSAttempt.bind(securityService),
  };

  return (
    <SecurityContext.Provider value={value}>
      {children}
    </SecurityContext.Provider>
  );
};
