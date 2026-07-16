<?php

namespace AdAstra\Services;

use AdAstra\Models\GateBypassLog;
use AdAstra\Models\User;
use AdAstra\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Buffers gate checks short-circuited by the super-admin Gate::before bypass
 * and writes them to gate_bypass_logs in a single insert at request/job
 * termination (see the terminating/Queue hooks in AppServiceProvider).
 *
 * Identical checks (same user + ability + subject) within one request are
 * deduped into a single row with an occurrence count — an admin page render
 * can fire dozens of gate checks, so per-check DB writes are off the table.
 *
 * Logging must never break authorization or the request: record() and flush()
 * swallow (and report()) every throwable.
 */
class GateBypassRecorder
{
    /**
     * Pending rows keyed by "userId|ability|subjectType|subjectId".
     *
     * @var array<string, array<string, mixed>>
     */
    private array $buffer = [];

    /**
     * Memoized security-domain settings for the current request/job.
     *
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function record(User $user, string $ability, array $arguments = []): void
    {
        try {
            if (!$this->shouldRecord()) {
                return;
            }

            [$subjectType, $subjectId] = $this->subjectFor($arguments);

            $key = $user->getKey() . '|' . $ability . '|' . ($subjectType ?? '') . '|' . ($subjectId ?? '');

            if (isset($this->buffer[$key])) {
                $this->buffer[$key]['occurrences']++;
                return;
            }

            $this->buffer[$key] = [
                'user_id' => $user->getKey(),
                'ability' => mb_substr($ability, 0, 255),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'occurrences' => 1,
            ];
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Write the buffered rows in one insert and reset. The buffer is swapped
     * out BEFORE the write so a failed insert can't retry-loop or leak rows
     * into the next queue job.
     *
     * @param array<string, mixed> $extraContext merged into the context column
     *                                           (e.g. the queue job class)
     */
    public function flush(array $extraContext = []): void
    {
        try {
            if ($this->buffer === []) {
                $this->reset();
                return;
            }

            $rows = array_values($this->buffer);
            $this->reset();

            $requestContext = $this->requestContext($extraContext);
            $now = now();

            foreach ($rows as &$row) {
                $row += $requestContext;
                $row['created_at'] = $now;
            }
            unset($row);

            // Bulk insert() bypasses casts and useCurrent, hence the explicit
            // created_at and the pre-encoded context in requestContext().
            GateBypassLog::insert($rows);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Clear the buffer and memoized settings (per queue job / for tests).
     */
    public function reset(): void
    {
        $this->buffer = [];
        $this->settings = null;
    }

    private function shouldRecord(): bool
    {
        $settings = $this->settings();

        if (!($settings['gate_bypass_log_enabled'] ?? true)) {
            return false;
        }

        $request = $this->httpRequest();

        // Console / queue bypasses always record — rare and high-signal.
        if ($request === null) {
            return true;
        }

        // Read-request bypasses are mostly passive UI checks (nav visibility,
        // button rendering) and are skipped unless explicitly enabled.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return (bool) ($settings['gate_bypass_log_include_reads'] ?? false);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        // system() — never get() — so the audited user's own overrides can't
        // influence an audit control. Settings caches per domain for 1h.
        return $this->settings ??= app(Settings::class)->system('security');
    }

    /**
     * The current HTTP request, or null when running from the console / a
     * queue worker. runningInConsole() alone is not enough: feature tests
     * report true while handling real HTTP requests, and console processes
     * carry a synthetic request (SetRequestForConsole) — but only a request
     * that went through routing has a matched route by the time a gate
     * check can run.
     */
    private function httpRequest(): ?Request
    {
        if (!app()->bound('request')) {
            return null;
        }

        $request = request();

        if (app()->runningInConsole() && $request->route() === null) {
            return null;
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $extraContext
     * @return array<string, mixed>
     */
    private function requestContext(array $extraContext): array
    {
        $request = $this->httpRequest();

        if ($request !== null) {
            return [
                'method' => $request->method(),
                'url' => mb_substr($request->fullUrl(), 0, 2048),
                'route_name' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'context' => $extraContext === [] ? null : json_encode($extraContext),
            ];
        }

        return [
            'method' => null,
            'url' => null,
            'route_name' => null,
            'ip' => null,
            'context' => json_encode(array_merge(
                ['command' => implode(' ', $_SERVER['argv'] ?? [])],
                $extraContext
            )),
        ];
    }

    /**
     * Derive [subject_type, subject_id] from the gate check's first argument:
     * a Model maps through the morphmap, a class string maps to its morph
     * alias (falling back to the raw string) with no id, anything else is
     * an argument-less ability.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function subjectFor(array $arguments): array
    {
        $subject = $arguments[0] ?? null;

        if ($subject instanceof Model) {
            $key = $subject->getKey();

            return [$subject->getMorphClass(), $key === null ? null : (string) $key];
        }

        if (is_string($subject) && $subject !== '') {
            $alias = array_search($subject, Relation::morphMap(), true);

            return [mb_substr($alias === false ? $subject : $alias, 0, 255), null];
        }

        return [null, null];
    }
}
