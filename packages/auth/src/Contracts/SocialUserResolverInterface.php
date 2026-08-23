<?php

namespace Tihloh\Prefab\Auth\Contracts;

use Tihloh\Prefab\Auth\Social\SocialIdentity;

interface SocialUserResolverInterface
{
    public function resolve(SocialIdentity $identity): ?AuthenticatableUserInterface;
}
