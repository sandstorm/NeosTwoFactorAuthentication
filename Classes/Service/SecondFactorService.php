<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Account;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Model\SecondFactor;
use Sandstorm\NeosTwoFactorAuthentication\Domain\Repository\SecondFactorRepository;

/**
 * @Flow\Scope("singleton")
 */
class SecondFactorService
{
    /**
     * @Flow\InjectConfiguration(path="enforceTwoFactorAuthentication")
     */
    protected bool $enforceTwoFactorAuthentication;

    /**
     * @Flow\InjectConfiguration(path="enforce2FAForAuthenticationProviders")
     */
    protected array $enforce2FAForAuthenticationProviders;

    /**
     * @Flow\InjectConfiguration(path="enforce2FAForRoles")
     */
    protected array $enforce2FAForRoles;

    /**
     * @Flow\Inject
     */
    protected SecondFactorRepository $secondFactorRepository;

    /**
     * Check if the second factor is enforced for the given account.
     *
     * The second factor is enforced if:
     *   - it is enforced for all accounts or
     *   - it is enforced for a role of the account or
     *   - it is enforced for the authentication provider of the account
     */
    public function isSecondFactorEnforcedForAccount(Account $account): bool
    {
        $isEnforcedForAll = $this->enforceTwoFactorAuthentication;
        $isEnforcedForRoles = count(array_intersect(
            array_map(fn($item) => $item->getIdentifier(), $account->getRoles()),
            $this->enforce2FAForRoles
        ));
        $isEnforcedForAuthenticationProviders = in_array(
            $account->getAuthenticationProviderName(),
            $this->enforce2FAForAuthenticationProviders
        );

        return $isEnforcedForAll || $isEnforcedForRoles || $isEnforcedForAuthenticationProviders;
    }

    /**
     * Check if the account has setup at least 1 second factor.
     */
    public function isSecondFactorEnabledForAccount(Account $account): bool
    {
        $factors = $this->secondFactorRepository->findByAccount($account);
        return count($factors) > 0;
    }

    /**
     * Check if the given OTP matches any registered second factor for the account.
     */
    public function validateOtpForAccount(string $otp, Account $account): bool
    {
        /** @var SecondFactor[] $secondFactors */
        $secondFactors = $this->secondFactorRepository->findByAccount($account);
        foreach ($secondFactors as $secondFactor) {
            if (TOTPService::checkIfOtpIsValid($secondFactor->getSecret(), $otp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the account can delete 1 second factor.
     *
     * Second factor can only be deleted if it is not enforced for the account or if the account has multiple factors.
     */
    public function canOneSecondFactorBeDeletedForAccount(Account $account): bool
    {
        $isEnforcedForAccount = $this->isSecondFactorEnforcedForAccount($account);
        $hasMultipleFactors = count($this->secondFactorRepository->findByAccount($account)) > 1;

        return !$isEnforcedForAccount || $hasMultipleFactors;
    }
}
