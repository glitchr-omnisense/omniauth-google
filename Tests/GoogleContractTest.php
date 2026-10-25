<?php

namespace Omniauth\Google\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Omniauth\Google\GoogleFactory;
use Omniauth\Google\Tests\Fixtures\Google;
use Omniauth\OAuth\Authorization;
use Omniauth\OAuth\Provider\Provider;
use Omniauth\OAuth\Provider\ProviderFactory;
use Omniauth\Testing\FixedClock;
use Omniauth\Testing\ProviderContractTests;
use PHPUnit\Framework\TestCase;

/** omniauth's provider contract, on Google's recorded answers (Fixtures/recorded). */
final class GoogleContractTest extends TestCase
{
    use ProviderContractTests;

    private ?Google $google = null;

    protected function provider(string $redirectUri): Provider
    {
        $this->google = new Google(new FixedClock('2026-10-21 12:00:00 UTC'), redirect: $redirectUri);

        return $this->google->provider;
    }

    private function google(): Google
    {
        return $this->google ?? throw new \LogicException('provider() first.');
    }

    protected function consented(Provider $provider, Authorization $authorization, string $authorizationUrl): string
    {
        $this->google()->token($authorization->nonce);

        return '4/0AQSTgQ-recorded-code';
    }

    protected function refused(Provider $provider): string
    {
        $this->google()->invalidGrant();

        return '4/0AQSTgQ-spent-code';
    }

    protected function unanswered(Provider $provider): string
    {
        $this->google()->answers->append(new Response(503, ['Content-Type' => 'text/html'], '<html>Service Unavailable</html>'));

        return '4/0AQSTgQ-recorded-code';
    }

    protected function factory(): ProviderFactory
    {
        return new GoogleFactory(collaborators: ['httpClient' => new Client(['handler' => HandlerStack::create(new MockHandler())])]);
    }

    /** @return array<string, string> */
    protected function entry(): array
    {
        return ['type' => 'google', 'client_id' => Google::CLIENT_ID, 'client_secret' => Google::CLIENT_SECRET, 'redirect_uri' => '/login/google/callback'];
    }

    protected function secret(): string
    {
        return Google::CLIENT_SECRET;
    }
}
