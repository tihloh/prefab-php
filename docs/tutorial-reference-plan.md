# Tutorial and Reference Scope

The website tutorial is designed for developers who have at least a basic background in plain PHP, including variables, arrays, functions, classes, `require`, forms, and basic database concepts.

It must not assume prior Laravel/framework experience.

## Required beginner path

1. Minimum software prerequisites
2. Verify PHP CLI and extensions
3. Composer installation/verification
4. Explain stable vs development package availability
5. Create an empty Composer project
6. Verify Composer autoloading
7. Start PHP's development server
8. Add modules progressively
9. Include a checkpoint/troubleshooting path
10. Finish with production guidance

## Reference coverage

The page should document Routes, Database, Users and simple Groups, Input, Auth, Permissions, Logs, Files, Live, Theme, Notifications, Messaging, shared Prefab configuration, mandatory session isolation, auto-wiring, troubleshooting and production deployment.

Live coverage should introduce server-driven components, public state, `#[Action]`, `#[Locked]`, `pf:model`, reactive `.live` / `.debounce` / `.blur` fields, `pf:error`, field-scoped `pf:loading`, optional Prefab Input rules, application-owned availability/uniqueness checks, final-action revalidation, `pf:click`, `pf:submit`, signed snapshots, the Live endpoint and the security boundary without assuming JavaScript-framework experience.

Theme coverage should introduce application theme policy, light/dark/system modes, density, user preference policy, semantic Prefab tokens, normal Bootstrap markup, optional admin components, inline assets by default and optional published assets.

Examples should show the developer-facing API. Internal setup methods should not be shown unless a developer actually needs to call them.

When a package has not yet received a stable Packagist release, document the `:dev-main` development/testing option and clearly distinguish it from the normal stable installation command.