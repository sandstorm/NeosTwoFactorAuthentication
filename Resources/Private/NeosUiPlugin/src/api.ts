export interface SecondFactorStatusResponse {
    secondFactorRequired: boolean;
}

export interface VerifySecondFactorResponse {
    success: boolean;
    error?: string;
}

export async function fetchSecondFactorStatus(csrfToken: string): Promise<SecondFactorStatusResponse> {
    console.log('[2FA API] fetchSecondFactorStatus, CSRF token present:', !!csrfToken);

    const response = await fetch('/neos/api/second-factor-status', {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Flow-Csrftoken': csrfToken,
        },
    });

    console.log('[2FA API] fetchSecondFactorStatus response:', response.status, response.statusText);

    if (!response.ok) {
        const text = await response.text();
        console.error('[2FA API] fetchSecondFactorStatus error body:', text);
        throw new Error(`Status check failed: ${response.status} - ${text}`);
    }

    const json: SecondFactorStatusResponse = await response.json();
    console.log('[2FA API] fetchSecondFactorStatus result:', json);
    return json;
}

export async function verifySecondFactor(otp: string, csrfToken: string): Promise<VerifySecondFactorResponse> {
    console.log('[2FA API] verifySecondFactor, OTP length:', otp.length, ', CSRF token present:', !!csrfToken);

    const response = await fetch('/neos/api/second-factor-verify', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Flow-Csrftoken': csrfToken,
        },
        body: JSON.stringify({ otp }),
    });

    console.log('[2FA API] verifySecondFactor response:', response.status, response.statusText);

    const json: VerifySecondFactorResponse = await response.json();
    console.log('[2FA API] verifySecondFactor result:', json);
    return json;
}
