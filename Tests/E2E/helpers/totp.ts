import { generateSync } from 'otplib';

export function generateOtp(secret: string): string {
  return generateSync({ secret, strategy: 'totp' });
}
