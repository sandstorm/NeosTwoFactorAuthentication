<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Cose\Algorithm\Manager as CoseAlgorithmManager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Account;
use Psr\Http\Message\ServerRequestInterface;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Implements the WebAuthn registration and authentication ceremonies on top of
 * the web-auth/webauthn-lib library (v5).
 *
 * @Flow\Scope("singleton")
 */
class WebAuthnService
{
    /**
     * @Flow\InjectConfiguration(path="webAuthn.relyingPartyName")
     * @var string
     */
    protected $relyingPartyName;

    /**
     * @Flow\InjectConfiguration(path="webAuthn.relyingPartyId")
     * @var string|null
     */
    protected $relyingPartyId;

    /**
     * @Flow\InjectConfiguration(path="webAuthn.userVerification")
     * @var string
     */
    protected $userVerification;

    /**
     * @Flow\InjectConfiguration(path="webAuthn.timeout")
     * @var int
     */
    protected $timeoutMs;

    /**
     * @Flow\InjectConfiguration(path="webAuthn.securedRelyingPartyIds")
     * @var array<string>
     */
    protected $securedRelyingPartyIds = [];

    /**
     * @Flow\InjectConfiguration(path="webAuthn.passwordlessLoginEnabled")
     * @var bool
     */
    protected $passwordlessLoginEnabled = false;

    /**
     * @Flow\Inject
     * @var WebAuthnSerializerProvider
     */
    protected $serializerProvider;

    /**
     * Our own credential repository. In v5 the ceremony validators no longer call back into a
     * repository, so we look credentials up and persist counter bumps through this ourselves.
     *
     * @Flow\Inject
     * @var PublicKeyCredentialSourceRepositoryAdapter
     */
    protected $credentialSourceRepository;

    /**
     * @Flow\Inject
     * @var SecondFactorRepository
     */
    protected $secondFactorRepository;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * Build a registration options object that the browser passes to
     * `navigator.credentials.create()`.
     *
     * When $discoverable is true (and passwordless login is enabled) the credential is
     * registered as a resident, user-verified passkey usable for passwordless login. Otherwise
     * it is registered as a plain second factor: no resident key, and user verification follows
     * the configured `webAuthn.userVerification` level — so a touch-only security key (no PIN)
     * keeps working as a 2nd factor even while passwordless login is enabled.
     */
    public function createRegistrationOptions(Account $account, string $hostname, bool $discoverable = false): PublicKeyCredentialCreationOptions
    {
        $rp = new PublicKeyCredentialRpEntity(
            $this->relyingPartyName ?: 'Neos',
            $this->relyingPartyId ?: $hostname
        );

        $userEntity = $this->buildUserEntity($account);

        // Exclude already-registered credentials so the browser refuses to register the same key twice.
        $excludeCredentials = array_map(
            fn(CredentialRecord $src): PublicKeyCredentialDescriptor => $src->getPublicKeyCredentialDescriptor(),
            $this->credentialSourceRepository->findAllForUserEntity($userEntity)
        );

        $challenge = random_bytes(32);

        $publicKeyCredentialParametersList = [
            PublicKeyCredentialParameters::create('public-key', ECDSA\ES256::ID),
            PublicKeyCredentialParameters::create('public-key', ECDSA\ES384::ID),
            PublicKeyCredentialParameters::create('public-key', ECDSA\ES512::ID),
            PublicKeyCredentialParameters::create('public-key', RSA\RS256::ID),
            PublicKeyCredentialParameters::create('public-key', EdDSA\Ed25519::ID),
        ];

        // Register a discoverable (resident), user-verified passkey only when the user opted into
        // it AND passwordless login is enabled — such a credential works both for one-tap
        // usernameless login AND as a strong second factor. The guard means a discoverable
        // credential can never be minted while passwordless login is off. Any other registration
        // keeps the configured user-verification level and no resident-key requirement, so
        // touch-only / U2F-only keys keep working as a plain 2nd factor.
        $residentKey = AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_NO_PREFERENCE;
        $userVerification = $this->userVerification;
        if ($discoverable && $this->passwordlessLoginEnabled) {
            $residentKey = AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED;
            $userVerification = AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED;
        }

        // v5 options objects are immutable — configured via named constructor arguments, not setters.
        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            userVerification: $userVerification,
            residentKey: $residentKey,
        );

        return PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            pubKeyCredParams: $publicKeyCredentialParametersList,
            authenticatorSelection: $authenticatorSelection,
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
            timeout: $this->timeoutMs,
        );
    }

    /**
     * Whether the given registration options describe a discoverable (resident) passkey. This is
     * the single source of truth for the stored `discoverable` flag: it reflects exactly what was
     * requested when the options were created, so it round-trips with the choice made there.
     */
    public function isDiscoverableRegistration(PublicKeyCredentialCreationOptions $options): bool
    {
        return ($options->authenticatorSelection?->residentKey ?? null)
            === AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED;
    }

    /**
     * Verify the attestation response returned by the browser and persist
     * the new credential as a SecondFactor row.
     *
     * @throws \Throwable when validation fails
     */
    public function verifyAndPersistRegistration(
        string $attestationResponseJson,
        PublicKeyCredentialCreationOptions $options,
        Account $account,
        ServerRequestInterface $request,
        string $name = ''
    ): SecondFactor {
        $publicKeyCredential = $this->serializerProvider->getSerializer()
            ->deserialize($attestationResponseJson, PublicKeyCredential::class, 'json');
        $authenticatorResponse = $publicKeyCredential->response;
        if (!$authenticatorResponse instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAttestationResponse', 1747750000);
        }

        $validator = AuthenticatorAttestationResponseValidator::create(
            $this->buildCeremonyStepManagerFactory()->creationCeremony()
        );
        $credentialRecord = $validator->check($authenticatorResponse, $options, $request->getUri()->getHost());

        // Whether this credential is a discoverable "Passkey" is derived from the options it was
        // registered with (see isDiscoverableRegistration / createRegistrationOptions): a passkey
        // registration requested a resident key, a 2nd-factor registration did not. This keeps the
        // stored flag faithful to the per-registration choice rather than the global setting.
        return $this->secondFactorRepository->createSecondFactorForAccount(
            $this->serializerProvider->getSerializer()->serialize($credentialRecord, 'json'),
            $account,
            SecondFactor::TYPE_PUBLIC_KEY,
            $name,
            $this->isDiscoverableRegistration($options)
        );
    }

    /**
     * Build a request options object that the browser passes to
     * `navigator.credentials.get()`.
     */
    public function createAuthenticationOptions(Account $account): PublicKeyCredentialRequestOptions
    {
        $userEntity = $this->buildUserEntity($account);
        $allowedCredentials = array_map(
            fn(CredentialRecord $src): PublicKeyCredentialDescriptor => $src->getPublicKeyCredentialDescriptor(),
            $this->credentialSourceRepository->findAllForUserEntity($userEntity)
        );

        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $this->relyingPartyId,
            allowCredentials: $allowedCredentials,
            userVerification: $this->userVerification,
            timeout: $this->timeoutMs,
        );
    }

    /**
     * Verify the assertion response returned by the browser. On success returns
     * the updated credential (counter bumped) which is also persisted.
     *
     * @throws \Throwable when validation fails
     */
    public function verifyAuthenticationResponse(
        string $assertionResponseJson,
        PublicKeyCredentialRequestOptions $options,
        Account $account,
        ServerRequestInterface $request
    ): CredentialRecord {
        $publicKeyCredential = $this->serializerProvider->getSerializer()
            ->deserialize($assertionResponseJson, PublicKeyCredential::class, 'json');
        $authenticatorResponse = $publicKeyCredential->response;
        if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAssertionResponse', 1747750001);
        }

        // In v5 check() takes the stored credential itself, so we look it up first.
        $credentialSource = $this->credentialSourceRepository->findOneByCredentialId($publicKeyCredential->rawId);
        if ($credentialSource === null) {
            throw new \RuntimeException('Unknown credential', 1747750002);
        }

        $userHandle = $this->buildUserHandle($account);
        $validator = AuthenticatorAssertionResponseValidator::create(
            $this->buildCeremonyStepManagerFactory()->requestCeremony()
        );

        $updatedCredential = $validator->check(
            $credentialSource,
            $authenticatorResponse,
            $options,
            $request->getUri()->getHost(),
            $userHandle
        );

        // v5 no longer persists the credential itself; save the bumped counter ourselves.
        $this->credentialSourceRepository->saveCredential($updatedCredential);

        return $updatedCredential;
    }

    /**
     * Build request options for a usernameless (passwordless) login: no allowed
     * credentials, so the browser offers any discoverable credential for this
     * relying party, and user verification is required (a verified passkey is a
     * full multi-factor authentication on its own).
     */
    public function createPasswordlessAuthenticationOptions(string $hostname): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $this->relyingPartyId ?: $hostname,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: $this->timeoutMs,
        );
    }

    /**
     * Verify a usernameless assertion and resolve the Neos backend account it belongs to.
     *
     * The assertion carries both the credential id and a user handle. We look the credential
     * up by its id first (proving we actually stored it), then let the library verify the
     * assertion against that credential's stored user handle, and finally map the user handle
     * (the account's persistence identifier) back to the Flow account. Only Neos backend
     * accounts may use this login path.
     *
     * @throws \Throwable when validation fails or no matching backend account exists
     */
    public function verifyPasswordlessAssertion(
        string $assertionResponseJson,
        PublicKeyCredentialRequestOptions $options,
        ServerRequestInterface $request
    ): Account {
        $publicKeyCredential = $this->serializerProvider->getSerializer()
            ->deserialize($assertionResponseJson, PublicKeyCredential::class, 'json');
        $authenticatorResponse = $publicKeyCredential->response;
        if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAssertionResponse', 1751200000);
        }

        $rawId = $publicKeyCredential->rawId;
        $credentialSource = $this->credentialSourceRepository->findOneByCredentialId($rawId);
        if ($credentialSource === null) {
            throw new \RuntimeException('Unknown passkey credential', 1751200001);
        }

        $userHandle = $credentialSource->userHandle;
        $validator = AuthenticatorAssertionResponseValidator::create(
            $this->buildCeremonyStepManagerFactory()->requestCeremony()
        );
        $updatedCredential = $validator->check(
            $credentialSource,
            $authenticatorResponse,
            $options,
            $request->getUri()->getHost(),
            $userHandle
        );

        // Persist the bumped counter so replay protection stays effective across logins.
        $this->credentialSourceRepository->saveCredential($updatedCredential);

        return $this->resolveBackendAccountByUserHandle($userHandle);
    }

    /**
     * Map a passkey user handle (the account's persistence identifier) back to its Flow account,
     * guarding that it is a Neos backend account. Extracted so this security-critical mapping can
     * be unit-tested without WebAuthn crypto.
     *
     * @throws \RuntimeException when no matching Neos backend account exists
     */
    public function resolveBackendAccountByUserHandle(string $userHandle): Account
    {
        $account = $this->persistenceManager->getObjectByIdentifier($userHandle, Account::class);
        if (!$account instanceof Account) {
            throw new \RuntimeException('No account found for the passkey user handle', 1751200002);
        }
        // Guard: only Neos backend accounts may authenticate passwordlessly through this path.
        if ($account->getAuthenticationProviderName() !== 'Neos.Neos:Backend') {
            throw new \RuntimeException('Passkey does not belong to a Neos backend account', 1751200003);
        }

        return $account;
    }

    /**
     * Serialize registration/authentication options for transport to the browser and for storage
     * in the session across the options -> verify round trip. Centralised here so the controllers
     * do not touch webauthn-lib serialization directly.
     */
    public function optionsToJson(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        return $this->serializerProvider->getSerializer()->serialize($options, 'json');
    }

    public function creationOptionsFromJson(string $json): PublicKeyCredentialCreationOptions
    {
        return $this->serializerProvider->getSerializer()
            ->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
    }

    public function requestOptionsFromJson(string $json): PublicKeyCredentialRequestOptions
    {
        return $this->serializerProvider->getSerializer()
            ->deserialize($json, PublicKeyCredentialRequestOptions::class, 'json');
    }

    private function buildUserEntity(Account $account): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $account->getAccountIdentifier(),
            $this->buildUserHandle($account),
            $account->getAccountIdentifier()
        );
    }

    private function buildUserHandle(Account $account): string
    {
        // Account identifier of the form `username@provider` is not PII-free; the persistence
        // identifier (UUID) is a stable non-PII handle. Fall back to a hashed identifier if absent.
        $id = $this->persistenceManager->getIdentifierByObject($account);
        if (!is_string($id) || $id === '') {
            $id = hash('sha256', $account->getAccountIdentifier(), true);
        }
        return $id;
    }

    /**
     * Build the ceremony-step configuration shared by the attestation and assertion validators.
     *
     * In v4 the algorithm manager, attestation support, extension handler and secured relying party
     * ids were passed piecemeal to the validator constructors (and the origin/host came from the
     * PSR request). v5 collects all of this into a CeremonyStepManager. We deliberately leave
     * allowed-origins unset and configure `securedRelyingPartyId` instead, so origin validation
     * stays host-based (dynamic multi-domain) exactly as before; the request host is passed to
     * check() per call.
     */
    private function buildCeremonyStepManagerFactory(): CeremonyStepManagerFactory
    {
        $attestationStatementSupportManager = AttestationStatementSupportManager::create();
        $attestationStatementSupportManager->add(NoneAttestationStatementSupport::create());
        // FidoU2F is needed for U2F-only authenticators (e.g. YubiKey 4) registered via
        // the browser's U2F-compat fallback — they return `fido-u2f` attestation regardless
        // of the requested `attestation: none` conveyance preference.
        $attestationStatementSupportManager->add(FidoU2FAttestationStatementSupport::create());

        $algorithmManager = CoseAlgorithmManager::create()
            ->add(new ECDSA\ES256())
            ->add(new ECDSA\ES384())
            ->add(new ECDSA\ES512())
            ->add(new RSA\RS256())
            ->add(new EdDSA\Ed25519());

        $factory = new CeremonyStepManagerFactory();
        $factory->setAttestationStatementSupportManager($attestationStatementSupportManager);
        $factory->setAlgorithmManager($algorithmManager);
        $factory->setSecuredRelyingPartyId($this->securedRelyingPartyIds);

        return $factory;
    }
}
