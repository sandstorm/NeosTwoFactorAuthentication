import { authenticator } from 'otplib';

export function generateOtp(secret: string): string {
  return authenticator.generate(secret);
}
