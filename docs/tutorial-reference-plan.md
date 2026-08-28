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

The page should document Routes, Database, Users and simple Groups, Input, Auth, Permissions, Logs, Files, Notifications, Messaging, shared Prefab configuration, mandatory session isolation, auto-wiring, troubleshooting and production deployment.

Examples should show the developer-facing API. Internal setup methods should not be shown unless a developer actually needs to call them.

When a package has not yet received a stable Packagist release, document the `:dev-main` development/testing option and clearly distinguish it from the normal stable installation command.