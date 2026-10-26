# omniauth/google

Google sign-in for [omnisense/omniauth](https://github.com/glitchr-omnisense/omniauth), under any PHP
host: Google as an OpenID Connect provider of omniauth's identity providers (`Omniauth\OAuth\`,
which carries what every provider shares - the state, PKCE and the nonce kept in the attempt, the
linking by `sub`, the sign-up of an unknown identity, the second factor after it). It is built on
league/oauth2-google 4 and omniauth's `LeagueProvider`, and calls:

| Step | Google | Address |
|---|---|---|
| the trip out (the browser) | the authorization endpoint | `https://accounts.google.com/o/oauth2/v2/auth` |
| the exchange (the server) | the token endpoint | `POST https://oauth2.googleapis.com/token` |
| the person, only when the ID token has no address | the userinfo endpoint | `GET https://openidconnect.googleapis.com/v1/userinfo` |

There is no sandbox option: the same endpoints serve every client.

## Installation

```sh
composer require omnisense/omniauth omniauth/google
```

Requires PHP 8.2, `omnisense/omniauth` (1.x), `league/oauth2-client` (^2.7), `league/oauth2-google`
(^4.0, Guzzle through it) and `psr/clock`. Under omnibridge/symfony its factory is registered as
soon as the package is installed (`omniauth.oauth.factory.google`); under omnibridge/laravel the
providers are made through it too.

## Credentials

- A **client id** and a **client secret** of an OAuth client of type "Web application", made in the
  Google Cloud console of a project (`…apps.googleusercontent.com`, `GOCSPX-…`).
- Its **authorized redirect URIs**: every return address the site uses, each host included - with a
  return path, `https://<each host><path>` (a site and its twin, a staging host).

## Options

`new GoogleProvider(...)`:

| Argument | Type | Default | |
|---|---|---|---|
| `clientId` | `string` | required | the client id; the ID token's audience must be it |
| `clientSecret` | `string` | required | |
| `redirectUri` | `string` | required | the return address, or its path on the host each trip begins at |
| `scopes` | `list<string>` | `GoogleProvider::SCOPES`: `openid`, `email`, `profile` | more scopes; the three are always sent |
| `hostedDomain` | `?string` | `null` | a Google Workspace domain the account must belong to (the ID token's `hd`), or `*` for any Workspace account; sent as `hd` in the address too |
| `clock` | `?Psr\Clock\ClockInterface` | `null`: the system's | the ID token's `exp` and `iat` |
| `collaborators` | `array` | `[]` | league's: an `httpClient` (a Guzzle client: tests, proxies) |
| `name` | `string` | `google` | the provider's key: the routes' `{provider}`, `Identities`' `$provider` |

`GoogleFactory` (for `Providers::configured()`, and the bridges' `oauth.providers`) makes an entry
named `google` (with no `type`) or of `type: google`, and returns null for any other:

| Key | Type | Default | |
|---|---|---|---|
| `client_id` | string | - | an entry without one is left out by `Providers::configured()` |
| `client_secret` | string | `''` | |
| `redirect_uri` | string | - | required by `Providers::configured()` |
| `scopes` | list | `[]`: `GoogleProvider::SCOPES` | |
| `hosted_domain` | string or null | `null` | |
| `type` | string | the entry's name | `google` |

`new GoogleFactory(?ClockInterface $clock = null, array $collaborators = [])`: the clock and league's
collaborators of every provider it makes. Constants: `GoogleProvider::ISSUERS`
(`https://accounts.google.com`, `accounts.google.com`), `GoogleProvider::LEEWAY` (60 seconds).

## Capabilities

| Of the `Provider` contract | |
|---|---|
| the authorization address: state, S256 challenge, nonce, return address, scopes | yes |
| the exchange of a code with its PKCE verifier | yes |
| the person from the ID token | yes, its claims checked (below) |
| the person from the userinfo | when the ID token is missing or has no `email` |
| a return path completed by each trip's origin | yes |
| the ID token's signature checked against Google's keys | no (see [Behaviour](google.md)) |
| refresh tokens, calls to Google's APIs | no: the access token serves the userinfo only, and is not kept |
| `prompt`, `access_type`, `login_hint` | not sent: league's options of the same names are not set |

## Example

A sign-in through the conductor, Google's token endpoint answered by a Guzzle `MockHandler` (run in
omniauth's harness on 2026-10-25, no network):

```php
<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Omniauth\ArrayState;
use Omniauth\Context;
use Omniauth\Google\GoogleProvider;
use Omniauth\Method\Methods;
use Omniauth\OAuth\NoSecondFactorLinking;
use Omniauth\OAuth\OAuthMethod;
use Omniauth\OAuth\Provider\Providers;
use Omniauth\Omniauth;
use Omniauth\Testing\InMemoryStore;
use Omniauth\Testing\TestAccount;

// Google's token endpoint, answered from here: no network.
$google = new MockHandler();
$provider = new GoogleProvider(
    clientId: '1234-example.apps.googleusercontent.com',
    clientSecret: 'GOCSPX-example',
    redirectUri: '/login/google/callback',
    collaborators: ['httpClient' => new Client(['handler' => HandlerStack::create($google)])],
);

$store = new InMemoryStore($ringo = new TestAccount('42', 'ringo', 'ringo@example.org'));
$session = [];
$methods = new Methods();
$methods->add(new OAuthMethod($store, $store, new Providers($provider), new NoSecondFactorLinking($methods)));
$omniauth = new Omniauth(accounts: $store, methods: $methods, state: new ArrayState($session));
$context = new Context(origin: 'https://example.org', host: 'example.org', ip: '203.0.113.7');

$address = $omniauth->begin('oauth', $context, ['provider' => 'google'])->payload;
parse_str((string) parse_url($address, PHP_URL_QUERY), $query);
echo implode(' ', array_keys($query)), "\n";

// The person consents; Google answers the exchange with an ID token carrying the trip's nonce.
$jwt = static fn (array $claims) => implode('.', array_map(static fn ($part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '='), [['alg' => 'RS256'], $claims, 'signature']));
$google->append(new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'access_token' => 'ya29.example', 'expires_in' => 3599, 'token_type' => 'Bearer',
    'id_token' => $jwt(['iss' => 'https://accounts.google.com', 'aud' => '1234-example.apps.googleusercontent.com', 'sub' => '108204',
        'email' => 'ringo@example.org', 'email_verified' => true, 'name' => 'Ringo Starr', 'nonce' => $query['nonce'], 'iat' => time(), 'exp' => time() + 3600]),
])));
$result = $omniauth->verify('oauth', $context, ['provider' => 'google', 'code' => '4/0Ab-example', 'state' => $query['state']]);
echo $result->status->value, ' as ', $result->account->identifier, ', linked to google ', $ringo->identities['google'], "\n";

// A code Google refuses.
$address = $omniauth->begin('oauth', $context, ['provider' => 'google'])->payload;
parse_str((string) parse_url($address, PHP_URL_QUERY), $query);
$google->append(new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'invalid_grant', 'error_description' => 'Bad Request'])));
$result = $omniauth->verify('oauth', $context, ['provider' => 'google', 'code' => '4/0Ab-used', 'state' => $query['state']]);
echo $result->failure->value, ': ', $result->reason, "\n";
```

```
state code_challenge code_challenge_method scope redirect_uri nonce response_type client_id
authenticated as ringo, linked to google 108204
bad-credentials: exchange-failed
```

In a real page the handler is left out (league uses its own Guzzle client), the start route
redirects to the address, and the return route hands its query to `verify()`.

Under Symfony (omnibridge/symfony):

```yaml
omniauth:
    oauth:
        providers:
            google:
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                # redirect_uri: unset, the path of the check route (/connect/google/check) on each trip's host
                # hosted_domain: example.org
```

Under Laravel (omnibridge/laravel), `config/omniauth.php`:

```php
'oauth' => ['providers' => ['google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
]]],
```

## Errors

Every refusal is an `Omniauth\OAuth\Exception\ProviderError`, which the OAuth method answers as
`bad-credentials` with its reason; the message is for the server's log:

| Reason | When |
|---|---|
| `exchange-failed` | the token endpoint refused the code (used, expired, not this trip's verifier or return address: `invalid_grant`) or did not answer; the userinfo request failed |
| `invalid-id-token` | the ID token is not a JWT; its issuer, audience, authorized party (`azp`, with several audiences), lifetime (`exp` past, 60 s of leeway) or nonce is not right, or it was issued in the future; the userinfo's subject is not the ID token's. Message: `The ID token's nonce is not right.` |
| `hosted-domain` | `hostedDomain` set and the ID token's `hd` is not it (with `*`: no `hd` at all); league's own check of the userinfo's domain |
| `no-subject` | the person has no `sub` |

The address of a trip begun without an origin, with a return path, is a `LogicException` (a host's
mistake): the `Context` needs its `origin`.

## Verified, and not

Tested offline in omniauth's harness, **Google's answers recorded** by hand from Google's OpenID
Connect documentation (a Guzzle mock handler, `Tests/Fixtures/recorded/`): the authorization
address (Google's endpoint, every parameter); the token request (POST to the token endpoint, the
code, the verifier whose S256 is the challenge that went out, the client, the return address); the
ID token's checks; `email_verified` as a boolean and as a string; the userinfo through the bearer
token, its subject compared; a code Google refuses; a sign-in through the conductor; a return path
on the host of each trip, the same at the exchange; the factory; the former names; in plain PHP
(`BareTest`), nothing loaded but omniauth, league, Guzzle, the PSR interfaces and the password
hasher. omniauth's provider conformance suite (`Tests/GoogleContractTest.php`) is green on the same
recorded answers (2026-10-25: omniauth/google's 28 tests in the harness run).

Not verified: **a real Google**. No client id and secret were at hand: the recorded answers are
written from Google's documentation, not captured, and the ID token's signature is not checked.

## Responsibility

An independent, unofficial client of Google's sign-in: not affiliated with, endorsed by or
sponsored by Google LLC. Whoever runs it holds the OAuth client and complies with Google's terms for
it. The MIT licence covers this package's code only.
