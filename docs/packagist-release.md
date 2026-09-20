# Packagist and Release Guide

Prefab is a **single development monorepo with unified versioning**. `tihloh/prefab-php` is the source of truth for every official module, test, example, document and release.

Individual `tihloh/prefab-*` repositories are **distribution mirrors**. They exist so Composer and Packagist can install modules independently; they are not separate development projects and do not have independent release versions.

```text
prefab-php (source of truth)
        │
        ├── packages/core ───────────► prefab-core ───────────► Packagist
        ├── packages/users ──────────► prefab-users ──────────► Packagist
        ├── packages/auth ───────────► prefab-auth ───────────► Packagist
        ├── packages/permissions ────► prefab-permissions ────► Packagist
        ├── packages/logs ───────────► prefab-logs ───────────► Packagist
        ├── packages/routes ─────────► prefab-routes ─────────► Packagist
        ├── packages/input ──────────► prefab-input ──────────► Packagist
        ├── packages/live ───────────► prefab-live ───────────► Packagist
        ├── packages/theme ──────────► prefab-theme ──────────► Packagist
        ├── packages/files ──────────► prefab-files ──────────► Packagist
        ├── packages/image ──────────► prefab-image ──────────► Packagist
        ├── packages/messaging ──────► prefab-messaging ──────► Packagist
        └── packages/notifications ──► prefab-notifications ──► Packagist
```

The retired standalone `prefab-database` package is not part of current releases. Database infrastructure belongs to Prefab Core.

## Source-of-truth rule

All development changes must be made in:

```text
tihloh/prefab-php
```

Never develop directly in a generated package mirror. If a module needs a fix, update `packages/<module>` in the monorepo, run CI, then publish the mirrors again.

## Unified versioning

Prefab uses **one version for the entire official package family**.

For a Prefab release such as `v0.2.0`:

```text
prefab-php              v0.2.0
├── prefab-core         v0.2.0
├── prefab-users        v0.2.0
├── prefab-auth         v0.2.0
├── prefab-live         v0.2.0
├── prefab-theme        v0.2.0
└── every other official mirror receives v0.2.0
```

A package receives the release tag even when that package's files did not change in that release. The version identifies the **Prefab release generation**, not the number of changes inside one module.

This keeps cross-module compatibility understandable while Prefab modules share Core infrastructure, runtime contracts, Auto-Wiring and interoperability behavior.

Do not describe official modules as having independent version lines in their documentation. Prefer wording such as “currently provides” or “current scope” instead of “Prefab Live v0.x provides”.

## Distribution mirrors

The package split workflow currently publishes these repositories:

```text
tihloh/prefab-core
tihloh/prefab-users
tihloh/prefab-auth
tihloh/prefab-permissions
tihloh/prefab-logs
tihloh/prefab-routes
tihloh/prefab-input
tihloh/prefab-live
tihloh/prefab-theme
tihloh/prefab-files
tihloh/prefab-image
tihloh/prefab-messaging
tihloh/prefab-notifications
```

Their `main` branches and version tags are generated from corresponding `packages/<module>` subtrees.

## One-time GitHub setup

Create each distribution repository once and give the package-split credential **Contents: Read and write** access to every mirror.

Add the credential to `tihloh/prefab-php` as the Actions secret:

```text
PREFAB_SPLIT_TOKEN
```

The workflow `.github/workflows/split-packages.yml` uses this token to push package subtrees and release tags.

Whenever a new official module is added:

1. create its `tihloh/prefab-<module>` mirror;
2. grant `PREFAB_SPLIT_TOKEN` access;
3. add the module to the split workflow matrix;
4. add its Packagist package after the first successful split.

## Before a release

Release from the monorepo, not from a package mirror.

Before tagging:

1. ensure intended changes are on `main`;
2. verify Prefab CI is green;
3. verify every package `composer.json` is valid;
4. run relevant package and integration smoke tests;
5. confirm documentation reflects new public APIs and modules;
6. confirm all split target repositories exist and the token can write to them.

If local generated/synchronized files are used by a workflow, also verify the working tree is clean after running their sync tools.

## Create a release

Create the release tag on **`tihloh/prefab-php` only**.

Example:

```bash
git checkout main
git pull origin main
git tag -a v0.2.0 -m "Prefab PHP v0.2.0"
git push origin v0.2.0
```

Pushing a `v*` tag starts the package split workflow. For each module, the workflow:

1. creates a subtree history from `packages/<module>`;
2. updates the generated mirror's `main` branch;
3. applies the same Prefab release tag to that mirror commit.

Release tags are immutable. The workflow intentionally refuses to move an existing package tag.

The workflow can also be run manually. Its optional `release_tag` input applies one unified tag to every mirror.

## Packagist setup

Each generated mirror is submitted to Packagist once. After that, enable automatic GitHub/Packagist updates so future tags are discovered normally.

Projects still install only the modules they need:

```bash
composer require tihloh/prefab-routes
composer require tihloh/prefab-live
composer require tihloh/prefab-theme
```

Independent installation and unified versioning solve different problems:

```text
Independent packages
    = install only needed capabilities

Unified Prefab version
    = know which package generation was released/tested together
```

## Composer dependency ranges

Unified release tags do not require every internal dependency to use an exact version.

Where compatibility allows it, package constraints should use an appropriate compatible range rather than forcing an exact patch version. This lets patch releases remain practical while preserving the shared Prefab release identity.

## Semantic versioning policy

Prefab follows semantic versioning as one package family:

- `0.x` — evolving public contracts; minor releases may include documented API changes while Prefab matures;
- patch releases — compatible fixes and hardening within the current release line;
- `1.0.0` — when core public contracts and interoperability behavior are considered stable.

Unified versioning should remain the default unless Prefab eventually reaches a scale where modules truly require independent release lifecycles. That is a future architecture decision, not something distribution mirrors imply.

## Why mirrors are necessary

Prefab remains one monorepo whether or not mirrors exist.

The mirrors solve a packaging constraint: Composer/Packagist expects an installable package's `composer.json` at the root of its VCS repository. Mirrors let users install modules independently without splitting development ownership or versioning:

```text
one source of truth
+
independently installable packages
+
one Prefab release version
```
