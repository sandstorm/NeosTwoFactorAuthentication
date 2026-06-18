export const state = {
  // used to track otp secrets (for testing deviceName-secret is enough) - when writing a test with multiple equal device names this would not suffice anymore
  deviceNameSecretMap: new Map<string, string>(),
}
