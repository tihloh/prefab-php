<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class LiveManager
{
    private SnapshotSigner $signer;
    private StateSerializer $serializer;
    private ActionInvoker $actions;
    private FormProcessor $forms;
    private mixed $csrfValidator;

    public function __construct(
        private ComponentRegistry $registry,
        string $signingKey,
        private string $endpoint = '/prefab/live',
        private ?string $csrfToken = null,
        ?callable $csrfValidator = null,
    ) {
        $this->signer = new SnapshotSigner($signingKey);
        $this->serializer = new StateSerializer();
        $this->actions = new ActionInvoker();
        $this->forms = new FormProcessor($this->serializer);
        $this->csrfValidator = $csrfValidator;
    }

    public function mount(string $name, array $params = []): string
    {
        $component = $this->registry->make($name);
        $name = strtolower(trim($name));
        $this->bindValidation($component);
        $component->__liveMount($params);

        $html = $component->render();
        $snapshot = $this->serializer->snapshot($component);
        $id = bin2hex(random_bytes(8));
        $checksum = $this->signer->sign($id, $name, $snapshot);
        $component->__liveDehydrate();

        $attributes = [
            'pf:component' => $name,
            'pf:id' => $id,
            'pf:endpoint' => $this->endpoint,
            'pf:snapshot' => $this->encodeSnapshot($snapshot),
            'pf:checksum' => $checksum,
        ];

        if ($this->csrfToken !== null && $this->csrfToken !== '') {
            $attributes['pf:csrf'] = $this->csrfToken;
        }

        return '<div ' . $this->attributes($attributes) . '>' . $html . '</div>';
    }

    public function handle(array $payload, ?string $csrfToken = null): array
    {
        $this->validateCsrf($csrfToken);

        $id = $this->requiredString($payload, 'id');
        $name = strtolower(trim($this->requiredString($payload, 'component')));
        $checksum = $this->requiredString($payload, 'checksum');
        $snapshot = $payload['snapshot'] ?? null;
        $updates = $payload['updates'] ?? [];
        $validate = $payload['validate'] ?? [];
        $action = $payload['action'] ?? null;

        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Prefab Live request snapshot must be an object/array.');
        }

        if (!is_array($updates)) {
            throw new InvalidArgumentException('Prefab Live request updates must be an object/array.');
        }

        if (!is_array($validate)) {
            throw new InvalidArgumentException('Prefab Live request validate field list must be an array.');
        }

        if (!$this->signer->verify($id, $name, $snapshot, $checksum)) {
            throw new RuntimeException('Prefab Live snapshot checksum is invalid.');
        }

        $component = $this->registry->make($name);
        $this->bindValidation($component);
        $this->serializer->hydrate($component, $snapshot);
        $component->__liveHydrate();
        $this->serializer->applyUpdates($component, $updates);

        $validate = array_values(array_unique(array_filter(
            array_map(static fn (mixed $field): string => trim((string) $field), $validate),
            static fn (string $field): bool => $field !== '',
        )));

        if ($validate !== []) {
            $this->forms->validate($component, $validate);
        }

        if ($action !== null) {
            if (!is_array($action)) {
                throw new InvalidArgumentException('Prefab Live action must be an object/array.');
            }

            $method = isset($action['method']) ? (string) $action['method'] : '';
            $params = $action['params'] ?? [];

            if (!is_array($params)) {
                throw new InvalidArgumentException('Prefab Live action params must be an array.');
            }

            $this->actions->invoke($component, $method, $params);
        }

        $html = $component->render();
        $nextSnapshot = $this->serializer->snapshot($component);
        $nextChecksum = $this->signer->sign($id, $name, $nextSnapshot);
        $component->__liveDehydrate();

        return [
            'id' => $id,
            'component' => $name,
            'html' => $html,
            'snapshot' => $nextSnapshot,
            'checksum' => $nextChecksum,
            'errors' => $component->errors(),
            'validated' => $component->__liveValidatedFields(),
        ];
    }

    public static function decodeRequest(string $json): array
    {
        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid Prefab Live JSON request: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($payload)) {
            throw new InvalidArgumentException('Prefab Live JSON request must decode to an object.');
        }

        return $payload;
    }

    private function bindValidation(Component $component): void
    {
        $component->__liveBindValidator(
            fn (Component $target, ?array $fields = null): bool => $this->forms->validate($target, $fields),
        );
    }

    private function validateCsrf(?string $token): void
    {
        if ($this->csrfValidator === null) {
            return;
        }

        if (($this->csrfValidator)($token) !== true) {
            throw new RuntimeException('Prefab Live CSRF validation failed.');
        }
    }

    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Prefab Live request field is required: {$key}");
        }

        return $value;
    }

    private function encodeSnapshot(array $snapshot): string
    {
        try {
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Prefab Live snapshot cannot be encoded: ' . $e->getMessage(), previous: $e);
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function attributes(array $attributes): string
    {
        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return implode(' ', $html);
    }
}
