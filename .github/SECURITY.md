# Security Policy

## Supported versions

AdAstra is in alpha. Only the most recent alpha release receives fixes.

| Version | Supported |
| --- | --- |
| 0.0.2 (Alpha 2) | Yes |
| 0.0.1 (Alpha 1) | No |

Alpha releases are not supported for production use. Please do not run AdAstra
against live end-user traffic or real user data until a stable release.

## Reporting a vulnerability

Please report security issues privately rather than opening a public issue.

**Preferred:** use GitHub's private vulnerability reporting at
[Security → Report a vulnerability](https://github.com/mithra62/ad-astra/security/advisories/new).

**Alternative:** email eric@mithra62.com with "AdAstra security" in the subject.

Please include:

- A description of the issue and its impact
- Steps to reproduce, or a proof of concept
- The AdAstra version and PHP version
- Any suggested remediation, if you have one

## What to expect

- Acknowledgement within 5 business days
- An assessment and rough remediation timeline within 10 business days
- Credit in the changelog and advisory, unless you'd rather stay anonymous

This is a solo-maintained project during alpha, so response times reflect one
person's calendar rather than a security team's rotation. Serious issues get
prioritized over feature work.

## Scope

In scope:

- Authentication and authorization bypasses
- Injection of any kind (SQL, template, header)
- Exposure of secrets, tokens, or credentials
- Privilege escalation between roles or permissions
- Media upload handling and path traversal
- Anything reachable through the REST API

Out of scope:

- Issues that require an already-compromised admin account
- Missing hardening headers with no demonstrated impact
- Vulnerabilities in third-party dependencies, which should be reported upstream
  (though a heads-up here is welcome)
- Anything arising from running AdAstra in production against the license terms

## Disclosure

Please give a reasonable window to ship a fix before publishing. Coordinated
disclosure is appreciated and will be credited.
