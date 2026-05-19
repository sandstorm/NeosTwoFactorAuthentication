<?php

namespace Sandstorm\NeosTwoFactorAuthentication\Domain\SecondFactorMethod;

use Neos\Flow\Annotations as Flow;

/**
 * Registry of all available second-factor methods.
 *
 * @Flow\Scope("singleton")
 */
class SecondFactorMethodRegistry
{
    /**
     * @var SecondFactorMethodInterface[]
     */
    private array $methodsByType = [];

    /**
     * @var SecondFactorMethodInterface[]
     */
    private array $methodsByIdentifier = [];

    /**
     * @Flow\Inject
     * @var TotpMethod
     */
    protected $totpMethod;

    /**
     * @Flow\Inject
     * @var WebAuthnMethod
     */
    protected $webAuthnMethod;

    public function initializeObject(): void
    {
        $this->register($this->totpMethod);
        $this->register($this->webAuthnMethod);
    }

    private function register(SecondFactorMethodInterface $method): void
    {
        $this->methodsByType[$method->getType()] = $method;
        $this->methodsByIdentifier[$method->getIdentifier()] = $method;
    }

    /**
     * @return SecondFactorMethodInterface[]
     */
    public function getAll(): array
    {
        return array_values($this->methodsByType);
    }

    public function getByType(int $type): ?SecondFactorMethodInterface
    {
        return $this->methodsByType[$type] ?? null;
    }

    public function getByIdentifier(string $identifier): ?SecondFactorMethodInterface
    {
        return $this->methodsByIdentifier[$identifier] ?? null;
    }
}
