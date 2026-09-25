<?php

namespace Tihloh\Prefab\Auth\Adapters;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;

final class PrefabUsersAuthenticatableUser implements AuthenticatableUserInterface
{
    public function __construct(
        private object $user,
        private string $passwordField = 'password',
    ) {}

    public function authId(): int|string{return $this->user->id;}

    public function authPasswordHash(): ?string
    {
        $value=method_exists($this->user,'get')
            ?$this->user->get($this->passwordField)
            :($this->user->{$this->passwordField}??null);
        return is_string($value)?$value:null;
    }

    public function authIsActive(): bool{return (bool)($this->user->active??true);}

    public function original(): object{return $this->user;}

    public function toArray(): array
    {
        return method_exists($this->user,'toArray')
            ?$this->user->toArray()
            :get_object_vars($this->user);
    }

    public function __get(string $name): mixed{return $this->user->{$name}??null;}
    public function __isset(string $name): bool{return isset($this->user->{$name});}
}
