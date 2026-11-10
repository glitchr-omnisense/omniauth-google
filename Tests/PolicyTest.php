<?php

namespace Omniauth\Google\Tests;

use GuzzleHttp\Psr7\Response;
use Omniauth\Google\GoogleProvider;
use Omniauth\Google\Tests\Fixtures\Google;
use Omniauth\OAuth\Authorization;
use Omniauth\OAuth\Exception\ProviderError;
use Omniauth\Testing\FixedClock;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Google's terms as data (policy.json), and what they must never do to a
 * sign-in: no limit holds one back, a refusal from Google refuses the
 * sign-in (fails closed) and does not hold back the next one.
 */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresItsPolicy(): void
    {
        $policy = UsagePolicy::fromFile(GoogleProvider::POLICY);
        $google = new Google(new FixedClock('2026-10-21 12:00:00 UTC'));

        self::assertInstanceOf(DeclaresUsagePolicyInterface::class, $google->provider);
        self::assertEquals($policy, $google->provider->usagePolicy());
        self::assertSame('google', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertNull($policy->rateLimits, 'Google publishes no figure for its OAuth endpoints: unknown, not none');
        self::assertSame(Permission::Forbidden, $policy->rights->redistribution, 'user data is never transferred nor sold');
        self::assertTrue($policy->requirements->key, 'the OAuth client');
        self::assertSame('https://developers.google.com/terms', $policy->provenance->terms);
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
        self::assertSame([], $google->sent, 'reading the policy calls nobody');
    }

    /**
     * Google answers 429 at the exchange: the sign-in is refused (a
     * ProviderError, as any refused exchange), and the next visitor's
     * sign-in is still sent to Google - no wait is kept that one visitor's
     * failure would impose on every other.
     */
    public function testATooManyRequestsRefusesThatSignInAndHoldsBackNoOther(): void
    {
        $google = new Google(new FixedClock('2026-10-21 12:00:00 UTC'));
        $google->answers->append(new Response(429, ['Content-Type' => 'application/json', 'Retry-After' => '3600'], '{"error":"rate_limit_exceeded"}'));

        try {
            $google->provider->identify('4/0AQSTgQ-first-code', Authorization::fresh());
            self::fail('A refused exchange signs nobody in.');
        } catch (ProviderError $e) {
            self::assertSame(ProviderError::EXCHANGE, $e->reason);
        }

        $nonce = 'n-'.bin2hex(random_bytes(8));
        $google->token($nonce);
        $identity = $google->provider->identify('4/0AQSTgQ-second-code', new Authorization('the-state', 'v'.str_repeat('x', 42), $nonce));

        self::assertCount(2, $google->sent, 'the second exchange went out');
        self::assertNotSame('', $identity->subject);
    }
}
