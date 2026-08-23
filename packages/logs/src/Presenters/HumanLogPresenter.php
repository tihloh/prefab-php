<?php

namespace Tihloh\Prefab\Logs\Presenters;

/**
 * Converts technical structured log records into compact, human-friendly text.
 *
 * The presenter never changes stored log data. It only creates a presentation
 * layer suitable for ordinary users while preserving the original record under
 * the `technical` key.
 *
 * Optional resolvers may translate actor/subject IDs into project-specific
 * names such as "Demo Admin", "Test User", or "Managers".
 */
final class HumanLogPresenter
{
    /**
     * Present one technical log record in a human-friendly structure.
     *
     * @param array $log Raw log record returned by the repository.
     * @param callable|null $actorResolver fn (int|string $id): ?string
     * @param callable|null $subjectResolver fn (string $type, int|string|null $id, array $log): ?string
     *
     * @return array{
     *     id:mixed,
     *     who:string,
     *     did:string,
     *     what:string,
     *     summary:string,
     *     details:array,
     *     when:mixed,
     *     technical:array
     * }
     */
    public function present(
        array $log,
        ?callable $actorResolver = null,
        ?callable $subjectResolver = null,
    ): array {
        $actor = $this->resolveActor(
            $actorResolver,
            $log['actor_id'] ?? null,
        );

        $subjectType = $this->words(
            (string) ($log['subject_type'] ?? 'item'),
        );

        $subject = $this->resolveSubject(
            $subjectResolver,
            $log,
            $subjectType,
        );

        $action = (string) ($log['action'] ?? '');
        $permission = $this->permissionName($log);

        return [
            'id' => $log['id'] ?? null,
            'who' => $actor,
            'did' => $this->actionLabel($action),
            'what' => $subject,
            'summary' => $this->summary(
                $action,
                $actor,
                $subject,
                $permission,
                $log,
            ),
            // Permission actions already describe the change in the sentence.
            // Hiding the duplicate Yes -> No line keeps the ordinary-user view compact.
            'details' => str_starts_with($action, 'permission.')
                ? []
                : $this->changeDetails($log['changes'] ?? []),
            'when' => $log['occurred_at'] ?? null,
            'technical' => $log,
        ];
    }

    /**
     * Present multiple log records using the same resolvers.
     */
    public function many(
        array $logs,
        ?callable $actorResolver = null,
        ?callable $subjectResolver = null,
    ): array {
        return array_map(
            fn (array $log): array => $this->present(
                $log,
                $actorResolver,
                $subjectResolver,
            ),
            $logs,
        );
    }

    private function summary(
        string $action,
        string $actor,
        string $subject,
        string $permission,
        array $log,
    ): string {
        return match ($action) {
            'permission.granted' => "$actor allowed $permission for $subject.",
            'permission.denied' => "$actor denied $permission for $subject.",
            'permission.cleared' => "$actor restored inherited $permission for $subject.",
            default => $this->genericSummary($action, $actor, $subject, $log),
        };
    }

    private function genericSummary(
        string $action,
        string $actor,
        string $subject,
        array $log,
    ): string {
        $verb = match (true) {
            str_contains($action, 'created') => 'created',
            str_contains($action, 'updated') => 'updated',
            str_contains($action, 'deleted') => 'deleted',
            str_contains($action, 'login') => 'signed in',
            str_contains($action, 'logout') => 'signed out',
            default => $this->actionLabel($action),
        };

        if (
            str_contains($action, 'login')
            || str_contains($action, 'logout')
        ) {
            return "$actor $verb.";
        }

        if ($actor === 'Someone' && !empty($log['message'])) {
            return (string) $log['message'];
        }

        return trim("$actor $verb $subject.");
    }

    /**
     * Get a friendly permission label from metadata/change keys.
     *
     * Modules may provide `permission_name` for the best display value. When it
     * is absent, the technical permission code is converted to readable words.
     */
    private function permissionName(array $log): string
    {
        $metadata = $log['metadata'] ?? [];

        $permission = is_array($metadata)
            ? ($metadata['permission_name'] ?? $metadata['permission'] ?? null)
            : null;

        if (!$permission && is_array($log['changes'] ?? null)) {
            $permission = array_key_first($log['changes']);
        }

        if (!$permission) {
            return 'permission';
        }

        return ucfirst(
            $this->words(
                str_replace('.', ' ', (string) $permission),
            ),
        );
    }

    private function actionLabel(string $action): string
    {
        return ucfirst(
            $this->words(
                str_replace('.', ' ', $action),
            ),
        );
    }

    private function resolveActor(?callable $resolver, mixed $id): string
    {
        if ($id === null || $id === '') {
            return 'Someone';
        }

        if ($resolver) {
            $value = $resolver($id);

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return "Someone #$id";
    }

    private function resolveSubject(
        ?callable $resolver,
        array $log,
        string $type,
    ): string {
        $id = $log['subject_id'] ?? null;

        if ($resolver) {
            $value = $resolver(
                $log['subject_type'] ?? null,
                $id,
                $log,
            );

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return $id === null
            ? $type
            : "$type #$id";
    }

    /**
     * Convert field-level technical changes into readable before/after values.
     * Sensitive credential fields are deliberately excluded.
     */
    private function changeDetails(array $changes): array
    {
        $details = [];
        $sensitiveFields = [
            'password',
            'password_hash',
            'token',
            'secret',
        ];

        foreach ($changes as $field => $change) {
            if (!is_array($change)) {
                continue;
            }

            if (in_array((string) $field, $sensitiveFields, true)) {
                continue;
            }

            $details[] = [
                'field' => ucfirst($this->words((string) $field)),
                'old' => $this->friendlyValue($change['old'] ?? null),
                'new' => $this->friendlyValue($change['new'] ?? null),
            ];
        }

        return $details;
    }

    private function friendlyValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'None';
        }

        if ($value === true || $value === 1 || $value === '1') {
            return 'Yes';
        }

        if ($value === false || $value === 0 || $value === '0') {
            return 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) $value;
    }

    private function words(string $value): string
    {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                str_replace(['_', '-'], ' ', $value),
            ) ?? $value,
        );
    }
}
