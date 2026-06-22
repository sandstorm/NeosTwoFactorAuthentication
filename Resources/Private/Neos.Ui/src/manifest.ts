import manifest from '@neos-project/neos-ui-extensibility';
import reloadOnAuthTimeout from './reloadOnAuthTimeoutSaga';

manifest('Sandstorm.NeosTwoFactorAuthentication:ReloadOnAuthTimeout', {}, (globalRegistry: any) => {
    const sagasRegistry = globalRegistry.get('sagas');

    if (!sagasRegistry) {
        console.error('[2FA] sagas registry not found; cannot register reload-on-auth-timeout saga');
        return;
    }

    sagasRegistry.set('Sandstorm.NeosTwoFactorAuthentication/reloadOnAuthTimeout', {
        saga: reloadOnAuthTimeout,
    });
});
