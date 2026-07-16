<?php

namespace AdAstra\Doctor;

/**
 * A single finding produced by a DoctorCheck. Checks yield zero or more of
 * these from run(); yielding none is treated as a pass.
 *
 * Hard rule: messages and details must be safe to paste into a public GitHub
 * issue — presence and booleans only, never secret values.
 */
final class DoctorResult
{
    public function __construct(
        public readonly DoctorStatus $status,
        public readonly string $message,
        public readonly ?string $details = null,
        public readonly ?string $fixCommand = null,
        public readonly ?string $docsUrl = null,
    ) {
    }

    public static function pass(string $message): self
    {
        return new self(DoctorStatus::Pass, $message);
    }

    public static function warn(string $message, ?string $details = null, ?string $fixCommand = null, ?string $docsUrl = null): self
    {
        return new self(DoctorStatus::Warn, $message, $details, $fixCommand, $docsUrl);
    }

    public static function fail(string $message, ?string $details = null, ?string $fixCommand = null, ?string $docsUrl = null): self
    {
        return new self(DoctorStatus::Fail, $message, $details, $fixCommand, $docsUrl);
    }

    public static function skip(string $message): self
    {
        return new self(DoctorStatus::Skip, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'details' => $this->details,
            'fix_command' => $this->fixCommand,
            'docs_url' => $this->docsUrl,
        ];
    }
}
