import crypto from 'crypto';

export const generateSecureToken = (length: number = 32): string => {
  return crypto.randomBytes(length).toString('hex');
};

export const hashData = (data: string): string => {
  return crypto.createHash('sha256').update(data).digest('hex');
};

export const generateMFASecret = (): string => {
  return crypto.randomBytes(20).toString('base32');
};
