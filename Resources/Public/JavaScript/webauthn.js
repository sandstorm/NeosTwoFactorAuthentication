(function () {
    'use strict';

    function base64UrlToUint8Array(value) {
        var padded = value.replace(/-/g, '+').replace(/_/g, '/');
        var pad = padded.length % 4;
        if (pad) padded += '='.repeat(4 - pad);
        var binary = atob(padded);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }

    function uint8ArrayToBase64Url(bytes) {
        var binary = '';
        var arr = new Uint8Array(bytes);
        for (var i = 0; i < arr.length; i++) binary += String.fromCharCode(arr[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function decodeCreationOptions(options) {
        options.challenge = base64UrlToUint8Array(options.challenge);
        options.user.id = base64UrlToUint8Array(options.user.id);
        if (Array.isArray(options.excludeCredentials)) {
            options.excludeCredentials = options.excludeCredentials.map(function (c) {
                return Object.assign({}, c, { id: base64UrlToUint8Array(c.id) });
            });
        }
        return options;
    }

    function decodeRequestOptions(options) {
        options.challenge = base64UrlToUint8Array(options.challenge);
        if (Array.isArray(options.allowCredentials)) {
            options.allowCredentials = options.allowCredentials.map(function (c) {
                return Object.assign({}, c, { id: base64UrlToUint8Array(c.id) });
            });
        }
        return options;
    }

    function encodeAttestation(credential) {
        var response = credential.response;
        return {
            id: credential.id,
            rawId: uint8ArrayToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: uint8ArrayToBase64Url(response.clientDataJSON),
                attestationObject: uint8ArrayToBase64Url(response.attestationObject)
            }
        };
    }

    function encodeAssertion(credential) {
        var response = credential.response;
        return {
            id: credential.id,
            rawId: uint8ArrayToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: uint8ArrayToBase64Url(response.clientDataJSON),
                authenticatorData: uint8ArrayToBase64Url(response.authenticatorData),
                signature: uint8ArrayToBase64Url(response.signature),
                userHandle: response.userHandle ? uint8ArrayToBase64Url(response.userHandle) : null
            }
        };
    }

    async function postJson(url, body) {
        var response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: body !== undefined ? JSON.stringify(body) : null
        });
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            var msg = (data && data.message) ? data.message : 'Request failed with status ' + response.status;
            throw new Error(msg);
        }
        return data;
    }

    function showError(container, message) {
        var errEl = container.querySelector('[data-webauthn-error]');
        if (!errEl) return;
        var msgEl = errEl.querySelector('[data-webauthn-error-message]') || errEl;
        msgEl.textContent = message;
        errEl.style.display = '';
    }

    function clearError(container) {
        var errEl = container.querySelector('[data-webauthn-error]');
        if (errEl) {
            var msgEl = errEl.querySelector('[data-webauthn-error-message]') || errEl;
            msgEl.textContent = '';
            errEl.style.display = 'none';
        }
    }

    // Localized error strings rendered by Fusion into the container's
    // data-webauthn-messages attribute (keyed by DOMException name, plus
    // the fixed "result" / "default" / "unsupported" fallbacks).
    function getMessages(container) {
        try {
            return JSON.parse(container.dataset.webauthnMessages || '{}');
        } catch (err) {
            return {};
        }
    }

    // Turn a raw browser exception into a friendly message:
    // "<error name> - <result>: <possible reasons / what to do>".
    function describeError(container, e) {
        var messages = getMessages(container);
        var name = (e && e.name) ? e.name : 'Error';
        var hint = messages[name] || messages.default || (e && e.message) || '';
        return name + ' - ' + messages.result + ': ' + hint;
    }

    function checkSupport(container) {
        if (typeof window.PublicKeyCredential === 'undefined') {
            var trigger = container.querySelector('[data-webauthn-trigger]');
            if (trigger) trigger.disabled = true;
            showError(container, getMessages(container).unsupported);
            return false;
        }
        return true;
    }

    async function runRegistration(container) {
        clearError(container);
        var trigger = container.querySelector('[data-webauthn-trigger]');
        if (trigger) trigger.disabled = true;
        try {
            var options = await postJson(container.dataset.optionsUrl);
            var credential = await navigator.credentials.create({
                publicKey: decodeCreationOptions(options)
            });
            if (!credential) throw new Error('No credential returned by browser');
            var encoded = encodeAttestation(credential);
            var nameEl = container.querySelector('[data-webauthn-name]');
            var name = nameEl ? nameEl.value : '';
            await postJson(container.dataset.verifyUrl, { attestation: JSON.stringify(encoded), name: name });
            window.location.href = container.dataset.redirectUrl || '/neos';
        } catch (e) {
            showError(container, describeError(container, e));
            if (trigger) trigger.disabled = false;
        }
    }

    async function runAuthentication(container) {
        clearError(container);
        var trigger = container.querySelector('[data-webauthn-trigger]');
        if (trigger) trigger.disabled = true;
        try {
            var options = await postJson(container.dataset.optionsUrl);
            var credential = await navigator.credentials.get({
                publicKey: decodeRequestOptions(options)
            });
            if (!credential) throw new Error('No credential returned by browser');
            var encoded = encodeAssertion(credential);
            var result = await postJson(container.dataset.verifyUrl, { assertion: JSON.stringify(encoded) });
            window.location.href = (result && result.redirect) ? result.redirect : '/neos';
        } catch (e) {
            showError(container, describeError(container, e));
            if (trigger) trigger.disabled = false;
        }
    }

    function init() {
        document.querySelectorAll('[data-webauthn-register]').forEach(function (container) {
            if (!checkSupport(container)) return;
            var trigger = container.querySelector('[data-webauthn-trigger]');
            if (trigger) trigger.addEventListener('click', function () { runRegistration(container); });
        });

        document.querySelectorAll('[data-webauthn-login]').forEach(function (container) {
            if (!checkSupport(container)) return;
            var trigger = container.querySelector('[data-webauthn-trigger]');
            if (trigger) {
                trigger.addEventListener('click', function () { runAuthentication(container); });
                // Always auto-trigger so users with a security key get instant tap-and-go.
                // Users who prefer TOTP just dismiss the browser prompt and type their code.
                setTimeout(function () { runAuthentication(container); }, 200);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
