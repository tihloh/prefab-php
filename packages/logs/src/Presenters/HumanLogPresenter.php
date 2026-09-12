<?php

namespace Tihloh\Prefab\Logs\Presenters;

final class HumanLogPresenter
{
    public function present(array $log, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        $actor = $this->resolveActor($actorResolver, $log['actor_id'] ?? null);
        $subjectType = $this->words((string) ($log['subject_type'] ?? 'item'));
        $subject = $this->resolveSubject($subjectResolver, $log, $subjectType);
        $action = (string) ($log['action'] ?? '');
        $permission = $this->permissionName($log);
        $event = $this->summary($action, $actor, $subject, $permission, $log);

        return [
            'id' => $log['id'] ?? null,
            'who' => $actor,
            'did' => $this->actionLabel($action),
            'what' => $subject,
            'event' => $event,
            'summary' => $event,
            'details' => str_starts_with($action, 'permission.') ? [] : $this->changeDetails($log['changes'] ?? []),
            'created_at' => $log['created_at'] ?? $log['occurred_at'] ?? null,
            'occurred_at' => $log['occurred_at'] ?? null,
            'technical' => $log,
        ];
    }

    public function many(array $logs, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        return array_map(fn (array $log): array => $this->present($log, $actorResolver, $subjectResolver), $logs);
    }

    private function summary(string $action, string $actor, string $subject, string $permission, array $log): string
    {
        if (!empty($log['message'])) { return rtrim((string) $log['message'], '.') . '.'; }

        return match ($action) {
            'permission.granted' => "$actor allowed $permission for $subject.",
            'permission.denied' => "$actor denied $permission for $subject.",
            'permission.cleared' => "$actor restored inherited $permission for $subject.",
            default => $this->genericSummary($action, $actor, $subject),
        };
    }

    private function genericSummary(string $action, string $actor, string $subject): string
    {
        $verb = match (true) {
            str_contains($action, 'created') => 'created',
            str_contains($action, 'updated') => 'updated',
            str_contains($action, 'deleted') => 'deleted',
            str_contains($action, 'approved') => 'approved',
            str_contains($action, 'rejected') => 'rejected',
            str_contains($action, 'submitted') => 'submitted',
            str_contains($action, 'restored') => 'restored',
            str_contains($action, 'uploaded') => 'uploaded',
            str_contains($action, 'downloaded') => 'downloaded',
            str_contains($action, 'login') => 'signed in',
            str_contains($action, 'logout') => 'signed out',
            default => strtolower($this->actionLabel($action)),
        };

        if (str_contains($action, 'login') || str_contains($action, 'logout')) {
            return "$actor $verb.";
        }

        return trim("$actor $verb $subject.");
    }

    private function permissionName(array $log): string
    {
        $metadata = $log['metadata'] ?? [];
        $permission = is_array($metadata) ? ($metadata['permission_name'] ?? $metadata['permission'] ?? null) : null;
        if (!$permission && is_array($log['changes'] ?? null)) { $permission = array_key_first($log['changes']); }
        if (!$permission) { return 'permission'; }
        return ucfirst($this->words(str_replace('.', ' ', (string) $permission)));
    }

    private function actionLabel(string $action): string
    {
        return ucfirst($this->words(str_replace('.', ' ', $action)));
    }

    private function resolveActor(?callable $resolver, mixed $id): string
    {
        if ($id === null || $id === '') { return 'Someone'; }
        if ($resolver) {
            $value = $resolver($id);
            if ($value !== null && $value !== '') { return (string) $value; }
        }
        return "Someone #$id";
    }

    private function resolveSubject(?callable $resolver, array $log, string $type): string
    {
        $id = $log['subject_id'] ?? null;
        if ($resolver) {
            $value = $resolver($log['subject_type'] ?? null, $id, $log);
            if ($value !== null && $value !== '') { return (string) $value; }
        }
        return $id === null ? $type : "$type #$id";
    }

    private function changeDetails(array $changes): array
    {
        $details = [];
        $sensitiveFields = ['password', 'password_hash', 'token', 'secret', 'access_token', 'refresh_token', 'api_key', 'authorization', 'cookie'];

        foreach ($changes as $field => $change) {
            if (!is_array($change) || in_array(strtolower((string) $field), $sensitiveFields, true)) { continue; }

            $before = array_key_exists('before', $change) ? $change['before'] : ($change['old'] ?? null);
            $now = array_key_exists('now', $change) ? $change['now'] : ($change['new'] ?? null);
            if ($this->same($before, $now)) { continue; }

            $details[] = [
                'field' => ucfirst($this->words((string) $field)),
                'before' => $this->friendlyValue($before),
                'now' => $this->friendlyValue($now),
            ];
        }

        return $details;
    }

    private function same(mixed $before, mixed $now): bool
    {
        if (is_array($before) || is_array($now)) {
            return json_encode($before) === json_encode($now);
        }
        return $before === $now;
    }

    private function friendlyValue(mixed $value): string
    {
        if ($value === null || $value === '') { return 'None'; }
        if ($value === true || $value === 1 || $value === '1') { return 'Yes'; }
        if ($value === false || $value === 0 || $value === '0') { return 'No'; }
        if (is_array($value)) { return implode(', ', array_map('strval', $value)); }
        return (string) $value;
    }

    private function words(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $value)) ?? $value);
    }
}
