import manifest from '@neos-project/neos-ui-extensibility';
import ReloginDialogWithOtp from './ReloginDialogWithOtp';

interface Registry {
    get(key: string): any;
    set(key: string, value: any): void;
    _registry?: Map<string, unknown>;
}

interface GlobalRegistry {
    get(key: string): Registry | null;
    _registry?: Record<string, unknown>;
}

console.log('[2FA Plugin] manifest.tsx loaded');

manifest('Sandstorm.NeosTwoFactorAuthentication:ReloginDialog', {}, (globalRegistry: GlobalRegistry) => {
    console.log('[2FA Plugin] manifest callback executing');

    const containerRegistry = globalRegistry.get('containers');
    console.log('[2FA Plugin] containerRegistry:', containerRegistry);

    if (!containerRegistry) {
        console.error('[2FA Plugin] containerRegistry is null/undefined! Available registries:', globalRegistry._registry ? Object.keys(globalRegistry._registry) : 'unknown');
        return;
    }

    const existingKey = containerRegistry.get('Modals/ReloginDialog');
    console.log('[2FA Plugin] Existing Modals/ReloginDialog:', existingKey);

    // Replace with our 2FA-aware version (fully self-contained, uses same Redux/backend integration)
    containerRegistry.set(
        'Modals/ReloginDialog',
        ReloginDialogWithOtp
    );
    console.log('[2FA Plugin] Successfully registered ReloginDialogWithOtp');
});
