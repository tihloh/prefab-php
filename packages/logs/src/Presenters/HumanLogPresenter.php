<?php

namespace Tihloh\Prefab\Logs\Presenters;

final class HumanLogPresenter
{
    public function present(array $log, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        $actor = $this->resolve($actorResolver, $log['actor_id'] ?? null, 'Someone');
        $subjectType = $this->words((string)($log['subject_type'] ?? 'item'));
        $subject = $this->resolveSubject($subjectResolver, $log, $subjectType);
        $action = (string)($log['action'] ?? '');
        $permission = $this->permissionName($log);

        return [
            'id' => $log['id'] ?? null,
            'who' => $actor,
            'did' => $this->actionLabel($action),
            'what' => $subject,
            'summary' => $this->summary($action, $actor, $subject, $permission, $log),
            // Permission changes are already expressed in the summary; don't repeat Yes -> No below it.
            'details' => str_starts_with($action, 'permission.') ? [] : $this->changeDetails($log['changes'] ?? []),
            'when' => $log['occurred_at'] ?? null,
            'technical' => $log,
        ];
    }

    public function many(array $logs, ?callable $actorResolver = null, ?callable $subjectResolver = null): array
    {
        return array_map(fn(array $log) => $this->present($log, $actorResolver, $subjectResolver), $logs);
    }

    private function summary(string $action, string $actor, string $subject, ?string $permission, array $log): string
    {
        if ($action === 'permission.granted') return "$actor allowed {$permission} for $subject.";
        if ($action === 'permission.denied') return "$actor denied {$permission} for $subject.";
        if ($action === 'permission.cleared') return "$actor restored inherited {$permission} for $subject.";

        $verb = match (true) {
            str_contains($action, 'created') => 'created',
            str_contains($action, 'updated') => 'updated',
            str_contains($action, 'deleted') => 'deleted',
            str_contains($action, 'login') => 'signed in',
            str_contains($action, 'logout') => 'signed out',
            default => $this->actionLabel($action),
        };

        if (str_contains($action, 'login') || str_contains($action, 'logout')) return "$actor $verb.";
        if ($actor === 'Someone' && !empty($log['message'])) return (string)$log['message'];
        return trim("$actor $verb $subject.");
    }

    private function permissionName(array $log): string
    {
        $metadata = $log['metadata'] ?? [];
        $permission = is_array($metadata) ? ($metadata['permission_name'] ?? $metadata['permission'] ?? null) : null;
        if (!$permission && isset($log['changes']) && is_array($log['changes'])) $permission = array_key_first($log['changes']);
        return $permission ? ucfirst($this->words(str_replace('.', ' ', (string)$permission))) : 'permission';
    }

    private function actionLabel(string $action): string { return ucfirst($this->words(str_replace('.', ' ', $action))); }

    private function resolve(?callable $resolver, mixed $id, string $fallback): string
    {
        if ($id === null || $id === '') return $fallback;
        if ($resolver) { $value = $resolver($id); if ($value !== null && $value !== '') return (string)$value; }
        return "$fallback #$id";
    }

    private function resolveSubject(?callable $resolver, array $log, string $type): string
    {
        $id = $log['subject_id'] ?? null;
        if ($resolver) { $value = $resolver($log['subject_type'] ?? null, $id, $log); if ($value !== null && $value !== '') return (string)$value; }
        return $id === null ? $type : "$type #$id";
    }

    private function changeDetails(array $changes): array
    {
        $out = [];
        foreach ($changes as $field => $change) {
            if (!is_array($change) || in_array((string)$field, ['password','password_hash','token','secret'], true)) continue;
            $out[] = ['field'=>ucfirst($this->words((string)$field)),'old'=>$this->friendlyValue($change['old']??null),'new'=>$this->friendlyValue($change['new']??null)];
        }
        return $out;
    }

    private function friendlyValue(mixed $value): string
    {
        if ($value === null || $value === '') return 'None';
        if ($value === true || $value === 1 || $value === '1') return 'Yes';
        if ($value === false || $value === 0 || $value === '0') return 'No';
        if (is_array($value)) return implode(', ', array_map('strval', $value));
        return (string)$value;
    }

    private function words(string $value): string { return trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $value)) ?? $value); }
}
