# Changelog

Every version of omniauth/google, newest first ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
[Semantic Versioning](https://semver.org/)). A release's notes are its section here: the release
workflow refuses a tag without one.

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0`, `omnisense/omniauth` `^1.0` with no stability flag, so `composer require omniauth/google` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

Omniauth's Google sign-in, under any PHP host, over league/oauth2-google: Google as an OpenID Connect provider for omniauth's identity providers (Omniauth\OAuth\) - scopes "openid email profile", the nonce in the authorization address, the ID token's issuer, audience, authorized party, lifetime, nonce and Workspace domain ("hd") checked, "email_verified" read as Google sends it, the userinfo when there is no ID token -, its factory for a host's configuration.
