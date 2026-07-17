<!--
Sync Impact Report
- Version change: (template, unversioned) → 1.0.0
- Bump rationale: Initial ratification. Template placeholders replaced with
  concrete principles derived from the project (WordPress/Elementor plugin, PHP,
  PHPCS + WPCS, PHPUnit, WordPress.org distribution).
- Modified principles: All five defined for the first time —
  [PRINCIPLE_1] → I. WordPress Coding Standards Compliance
  [PRINCIPLE_2] → II. Security by Default
  [PRINCIPLE_3] → III. Test Discipline
  [PRINCIPLE_4] → IV. Compatibility & Minimum Requirements
  [PRINCIPLE_5] → V. Semantic Versioning & Release Discipline
- Added sections:
  [SECTION_2] → Technology & Distribution Standards
  [SECTION_3] → Development Workflow
- Removed sections: none
- Templates requiring updates:
  ✅ .specify/templates/plan-template.md (generic Constitution Check gate — no change needed)
  ✅ .specify/templates/spec-template.md (no constitution references — no change needed)
  ✅ .specify/templates/tasks-template.md (no constitution references — no change needed)
- Follow-up TODOs: none
-->

# Font Awesome Elementor Addon Constitution

## Core Principles

### I. WordPress Coding Standards Compliance
All PHP MUST pass the project's PHPCS ruleset (`plugin/phpcs.xml`, built on
`WordPress`, `WordPress-Extra`, and `WordPress.Security`) with zero errors before merge.
Code MUST use short array syntax, be fully internationalized with the
`fontawesome-elementor-addon` text domain, and follow PSR-4 autoloading under the
`FontAwesomeElementorAddon\` namespace. Rationale: Consistent, standards-compliant code
is required for WordPress.org distribution and keeps a small codebase reviewable and
maintainable.

### II. Security by Default
Every input MUST be sanitized, every output MUST be escaped, and every state-changing
request MUST verify a nonce and an appropriate capability check. Secrets such as Font
Awesome API Tokens MUST never be logged, echoed, or committed. The `WordPress.Security`
PHPCS rules are non-negotiable and MUST NOT be excluded. Rationale: The plugin handles
user credentials and runs in privileged WordPress admin contexts; a single lapse exposes
site owners to compromise.

### III. Test Discipline
Behavioral logic MUST be covered by PHPUnit tests using the configured Yoast WP test
utilities and polyfills. New features and bug fixes MUST add or update tests that fail
before the change and pass after it. Tests MUST run green locally before merge.
Rationale: Automated tests are the only scalable defense against regressions across the
WordPress, Elementor, and PHP version matrix this plugin supports.

### IV. Compatibility & Minimum Requirements
The plugin MUST run on the declared minimum versions — PHP 7.4+, WordPress 5.8+, and the
required Elementor plugin — and MUST degrade gracefully when Elementor or a valid Pro Kit
is unavailable rather than fataling. Declared "Requires" and "Tested up to" values in the
plugin header and `readme.txt` MUST be kept accurate. Rationale: Site owners run diverse
stacks; breaking their environment or silently failing erodes trust in an official
Font Awesome product.

### V. Semantic Versioning & Release Discipline
Version numbers MUST follow semantic versioning (MAJOR.MINOR.PATCH, with pre-release
suffixes such as `-alpha1` permitted). The version in the plugin header, `Plugin::PLUGIN_VERSION`,
and the `readme.txt` stable tag MUST stay in sync. Releases MUST be produced through the
`bin/build-release` script so distributed artifacts contain production dependencies only.
Rationale: Predictable versioning and reproducible builds protect the automatic-update
experience for every installed site.

## Technology & Distribution Standards

The plugin is a self-contained WordPress plugin written in PHP and distributed via the
WordPress.org plugin directory under a GPL-compatible license. Core constraints:

- Icons MUST be self-hosted (served from the user's own WordPress site); no CDN delivery
  of Kit assets at render time.
- Dependencies are managed with Composer; runtime code depends on
  `fortawesome/wordpress-fontawesome-lib`. Development tooling (PHPCS, PHPUnit) MUST stay
  in `require-dev` and be excluded from release artifacts.
- Local development uses `wp-env`; the `bin/` scripts are the supported entry points for
  linting, testing, and building.
- No tracking, telemetry, or third-party data sharing beyond the authenticated calls to
  Font Awesome required for Kit setup and download.

## Development Workflow

- Work happens on feature branches; changes reach `main` via pull request.
- Every PR MUST pass PHPCS (`composer phpcs`) and the PHPUnit suite before merge.
- Code review MUST verify compliance with the Core Principles, with particular attention
  to security (Principle II) and compatibility claims (Principle IV).
- `readme.txt` and the plugin header MUST be updated in the same change that alters
  behavior affecting users, supported versions, or the version number.

## Governance

This constitution supersedes other ad-hoc practices for this repository. Amendments MUST
be proposed via pull request, documented in the Sync Impact Report at the top of this
file, and approved by a project maintainer. Version bumps follow semantic versioning:
MAJOR for backward-incompatible governance or principle changes, MINOR for new or
materially expanded principles/sections, PATCH for clarifications and non-semantic
refinements. All PRs and reviews MUST verify compliance with the current principles;
justified, documented exceptions are permitted only when a principle explicitly allows
discretion (SHOULD-level guidance) and MUST be recorded in the PR description.

**Version**: 1.0.0 | **Ratified**: 2026-07-17 | **Last Amended**: 2026-07-17
