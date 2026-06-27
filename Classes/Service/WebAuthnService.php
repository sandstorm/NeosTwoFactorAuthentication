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
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Implements the WebAuthn registration and authentication ceremonies on top of
 * the web-auth/webauthn-lib library.
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
     * `lazy=false` so the real adapter (not a DependencyProxy) is passed into the
     * web-auth validator constructors, which strict-type-hint the interface.
     *
     * @Flow\Inject(lazy=false)
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
     */
    public function createRegistrationOptions(Account $account, string $hostname): PublicKeyCredentialCreationOptions
    {
        $rp = new PublicKeyCredentialRpEntity(
            $this->relyingPartyName ?: 'Neos',
            $this->relyingPartyId ?: $hostname
        );

        $userEntity = $this->buildUserEntity($account);

        // Exclude already-registered credentials so the browser refuses to register the same key twice.
        $excludeCredentials = array_map(
            fn(PublicKeyCredentialSource $src): PublicKeyCredentialDescriptor => $src->getPublicKeyCredentialDescriptor(),
            $this->credentialSourceRepository->findAllForUserEntity($userEntity)
        );

        $challenge = random_bytes(32);

        $publicKeyCredentialParametersList = [
            new PublicKeyCredentialParameters('public-key', ECDSA\ES256::ID),
            new PublicKeyCredentialParameters('public-key', ECDSA\ES384::ID),
            new PublicKeyCredentialParameters('public-key', ECDSA\ES512::ID),
            new PublicKeyCredentialParameters('public-key', RSA\RS256::ID),
            new PublicKeyCredentialParameters('public-key', EdDSA\Ed25519::ID),
        ];

        $authenticatorSelection = AuthenticatorSelectionCriteria::create()
            ->setUserVerification($this->userVerification);

        // When passwordless login is enabled, register discoverable (resident) credentials
        // with user verification so a single credential works both for one-tap usernameless
        // login AND as a strong second factor. When disabled the behaviour is unchanged, so
        // U2F-only keys (which cannot store a resident credential) keep working as a 2nd factor.
        if ($this->passwordlessLoginEnabled) {
            $authenticatorSelection
                ->setResidentKey(AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED)
                ->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED);
        }

        return PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            $publicKeyCredentialParametersList
        )
            ->setTimeout($this->timeoutMs)
            ->setAuthenticatorSelection($authenticatorSelection)
            ->setAttestation(PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE)
            ->excludeCredentials(...$excludeCredentials);
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
        $publicKeyCredentialLoader = $this->buildCredentialLoader();
        $publicKeyCredential = $publicKeyCredentialLoader->load($attestationResponseJson);
        $authenticatorResponse = $publicKeyCredential->getResponse();
        if (!$authenticatorResponse instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAttestationResponse', 1747750000);
        }

        $validator = $this->buildAttestationValidator();
        $credentialSource = $validator->check($authenticatorResponse, $options, $request, $this->securedRelyingPartyIds);

        return $this->secondFactorRepository->createSecondFactorForAccount(
            json_encode($credentialSource->jsonSerialize(), JSON_THROW_ON_ERROR),
            $account,
            SecondFactor::TYPE_PUBLIC_KEY,
            $name
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
            fn(PublicKeyCredentialSource $src): PublicKeyCredentialDescriptor => $src->getPublicKeyCredentialDescriptor(),
            $this->credentialSourceRepository->findAllForUserEntity($userEntity)
        );

        return PublicKeyCredentialRequestOptions::create(random_bytes(32))
            ->setTimeout($this->timeoutMs)
            ->setRpId($this->relyingPartyId)
            ->setUserVerification($this->userVerification)
            ->allowCredentials(...$allowedCredentials);
    }

    /**
     * Verify the assertion response returned by the browser. On success returns
     * the updated credential source (counter bumped) and the matching SecondFactor.
     *
     * @throws \Throwable when validation fails
     */
    public function verifyAuthenticationResponse(
        string $assertionResponseJson,
        PublicKeyCredentialRequestOptions $options,
        Account $account,
        ServerRequestInterface $request
    ): PublicKeyCredentialSource {
        $publicKeyCredentialLoader = $this->buildCredentialLoader();
        $publicKeyCredential = $publicKeyCredentialLoader->load($assertionResponseJson);
        $authenticatorResponse = $publicKeyCredential->getResponse();
        if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAssertionResponse', 1747750001);
        }

        $userHandle = $this->buildUserHandle($account);
        $validator = $this->buildAssertionValidator();

        return $validator->check(
            $publicKeyCredential->getRawId(),
            $authenticatorResponse,
            $options,
            $request,
            $userHandle,
            $this->securedRelyingPartyIds
        );
    }

    /**
     * Build request options for a usernameless (passwordless) login: no allowed
     * credentials, so the browser offers any discoverable credential for this
     * relying party, and user verification is required (a verified passkey is a
     * full multi-factor authentication on its own).
     */
    public function createPasswordlessAuthenticationOptions(string $hostname): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(random_bytes(32))
            ->setTimeout($this->timeoutMs)
            ->setRpId($this->relyingPartyId ?: $hostname)
            ->setUserVerification(PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED);
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
        $publicKeyCredentialLoader = $this->buildCredentialLoader();
        $publicKeyCredential = $publicKeyCredentialLoader->load($assertionResponseJson);
        $authenticatorResponse = $publicKeyCredential->getResponse();
        if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Response is not an AuthenticatorAssertionResponse', 1751200000);
        }

        $rawId = $publicKeyCredential->getRawId();
        $credentialSource = $this->credentialSourceRepository->findOneByCredentialId($rawId);
        if ($credentialSource === null) {
            throw new \RuntimeException('Unknown passkey credential', 1751200001);
        }

        $userHandle = $credentialSource->getUserHandle();
        $validator = $this->buildAssertionValidator();
        $validator->check(
            $rawId,
            $authenticatorResponse,
            $options,
            $request,
            $userHandle,
            $this->securedRelyingPartyIds
        );

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

    private function buildUserEntity(Account $account): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
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

    private function buildCredentialLoader(): PublicKeyCredentialLoader
    {
        $attestationManager = $this->buildAttestationStatementSupportManager();
        $attestationObjectLoader = new AttestationObjectLoader($attestationManager);
        return new PublicKeyCredentialLoader($attestationObjectLoader);
    }

    private function buildAttestationValidator(): AuthenticatorAttestationResponseValidator
    {
        return new AuthenticatorAttestationResponseValidator(
            $this->buildAttestationStatementSupportManager(),
            $this->credentialSourceRepository,
            null,
            new ExtensionOutputCheckerHandler()
        );
    }

    private function buildAttestationStatementSupportManager(): AttestationStatementSupportManager
    {
        $manager = new AttestationStatementSupportManager();
        $manager->add(new NoneAttestationStatementSupport());
        // FidoU2F is needed for U2F-only authenticators (e.g. YubiKey 4) registered via
        // the browser's U2F-compat fallback — they return `fido-u2f` attestation regardless
        // of the requested `attestation: none` conveyance preference.
        $manager->add(new FidoU2FAttestationStatementSupport());
        return $manager;
    }

    private function buildAssertionValidator(): AuthenticatorAssertionResponseValidator
    {
        $algorithmManager = CoseAlgorithmManager::create()
            ->add(new ECDSA\ES256())
            ->add(new ECDSA\ES384())
            ->add(new ECDSA\ES512())
            ->add(new RSA\RS256())
            ->add(new EdDSA\Ed25519());

        return new AuthenticatorAssertionResponseValidator(
            $this->credentialSourceRepository,
            null,
            new ExtensionOutputCheckerHandler(),
            $algorithmManager
        );
    }
}
