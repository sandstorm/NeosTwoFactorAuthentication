<?php

declare(strict_types=1);

namespace Sandstorm\NeosTwoFactorAuthentication\UserManagement\Application;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\BaseUriProvider;
use Neos\Flow\I18n\Locale;
use Neos\Flow\I18n\Service as I18nService;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Domain\Model\User;
use Neos\SymfonyMailer\Service\MailerService;
use Psr\Http\Message\ResponseInterface;
use Sandstorm\NeosTwoFactorAuthentication\UserManagement\Domain\UserActionType;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends the user management mails via neos/symfonymailer. Bodies (HTML + plain text) are rendered from
 * Fusion (Resources/Private/Fusion/UserManagement/Mail) in the recipient's backend interface language.
 *
 * Throws on any transport or configuration error - callers decide whether to show or swallow it.
 */
#[Flow\Scope('singleton')]
class UserMailer
{
    /**
     * Paths of the public routes, see Configuration/Routes.yaml
     */
    public const SET_PASSWORD_PATH = '/neos/password/set/';
    public const CONFIRM_EMAIL_PATH = '/neos/email/confirm/';

    #[Flow\Inject]
    protected MailerService $mailerService;

    #[Flow\Inject]
    protected BaseUriProvider $baseUriProvider;

    #[Flow\Inject]
    protected I18nService $i18nService;

    /** @var array{from?: array{address?: ?string, name?: ?string}} */
    #[Flow\InjectConfiguration(path: 'userManagement.mail')]
    protected array $mailSettings = [];

    #[Flow\InjectConfiguration(package: 'Neos.Neos', path: 'userInterface.defaultLanguage')]
    protected ?string $defaultLanguage = null;

    /**
     * One mail with one link per account (several accounts may share the same email address).
     *
     * @param list<array{username: string, token: string}> $links
     */
    public function sendPasswordLinks(string $recipient, ?User $user, UserActionType $action, array $links, \DateTimeImmutable $expiresAt): void
    {
        $this->send($recipient, $user, 'passwordLinks', [
            'action' => $action->value,
            'links' => array_map(fn (array $link) => [
                'username' => $link['username'],
                'uri' => $this->buildUri(self::SET_PASSWORD_PATH . $link['token']),
            ], $links),
            'expiresAt' => $expiresAt,
            'name' => $user?->getName()->getFullName() ?? '',
        ]);
    }

    public function sendEmailVerification(string $recipient, User $user, string $token, \DateTimeImmutable $expiresAt): void
    {
        $this->send($recipient, $user, 'emailVerification', [
            'uri' => $this->buildUri(self::CONFIRM_EMAIL_PATH . $token),
            'expiresAt' => $expiresAt,
            'name' => $user->getName()->getFullName(),
        ]);
    }

    public function sendTestMail(string $recipient, ?User $user): void
    {
        $this->send($recipient, $user, 'testMail', [
            'name' => $user?->getName()->getFullName() ?? '',
        ]);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function send(string $recipient, ?User $user, string $mailName, array $variables): void
    {
        $fromAddress = $this->mailSettings['from']['address'] ?? null;
        if ($fromAddress === null || $fromAddress === '') {
            throw new \RuntimeException('Sandstorm.NeosTwoFactorAuthentication.userManagement.mail.from.address is not configured.', 1760000001);
        }

        $locale = $user?->getPreferences()->getInterfaceLanguage() ?? $this->defaultLanguage ?? 'en';
        $subject = $this->render($mailName . '.subject', $variables, $locale);
        $html = $this->render($mailName . '.html', $variables, $locale);
        $text = $this->render($mailName . '.text', $variables, $locale);

        $email = (new Email())
            ->from(new Address($fromAddress, $this->mailSettings['from']['name'] ?? ''))
            ->to($recipient)
            ->subject(trim($subject))
            ->html($html)
            ->text($text);

        $this->mailerService->getMailer()->send($email);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $fusionPath, array $variables, string $localeIdentifier): string
    {
        $i18nConfiguration = $this->i18nService->getConfiguration();
        $previousLocale = $i18nConfiguration->getCurrentLocale();
        try {
            $i18nConfiguration->setCurrentLocale(new Locale($localeIdentifier));

            $view = new FusionView();
            $view->setFusionPathPatterns(['resource://Sandstorm.NeosTwoFactorAuthentication/Private/Fusion']);
            $view->setFusionPath('Sandstorm/NeosTwoFactorAuthentication/UserManagement/Mail/' . str_replace('.', '/', $fusionPath));
            $view->assignMultiple($variables);
            $output = $view->render();
            return $output instanceof ResponseInterface ? (string)$output->getBody() : (string)$output;
        } finally {
            $i18nConfiguration->setCurrentLocale($previousLocale);
        }
    }

    private function buildUri(string $path): string
    {
        return rtrim((string)$this->baseUriProvider->getConfiguredBaseUriOrFallbackToCurrentRequest(), '/') . $path;
    }
}
