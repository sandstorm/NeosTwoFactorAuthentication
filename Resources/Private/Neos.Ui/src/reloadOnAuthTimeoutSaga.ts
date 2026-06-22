import { take } from 'redux-saga/effects';
import { actionTypes } from '@neos-project/neos-ui-redux-store';

/**
 * Reloads the page when the Neos backend session times out.
 *
 * Neos core dispatches `@neos/neos-ui/System/AUTHENTICATION_TIMEOUT` on a 401 —
 * the very same action that triggers the core ReloginDialog. For accounts with
 * a second factor the client-side relogin cannot complete the 2FA step, so
 * instead of relying on the dialog we reload the page and let the server-side
 * login flow (which handles 2FA) take over.
 *
 * Uses the exported `actionTypes` constant rather than the literal string so we
 * stay bound to the core contract. The action type is identical in Neos 8 and 9.
 */
export default function* reloadOnAuthTimeout() {
    yield take(actionTypes.System.AUTHENTICATION_TIMEOUT);
    window.location.reload();
}
