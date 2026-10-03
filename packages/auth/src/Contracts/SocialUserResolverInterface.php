<?php

namespace Tihloh\Prefab\Auth\Contracts;

use Tihloh\Prefab\Auth\Social\SocialIdentity;

/**
 * Resolves an unlinked external identity to a local user.
 *
 * The host application owns registration policy. A resolver may create a new
 * local user, or return a user only after the application has independently
 * approved that association. It should not silently claim an existing local
 * account merely because the external provider returned the same email.
 */
interface SocialUserResolverInterface
{
    public function resolve(
        SocialIdentity $identity,
    ): ?AuthenticatableUserInterface;
}
