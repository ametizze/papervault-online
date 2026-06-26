<?php

declare(strict_types=1);

namespace SimpleVault\Core;

/**
 * Minimal input validator. Accumulates errors keyed by field name.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->addError($field, "$label is required.");
        }

        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = $this->data[$field] ?? '';
        if (is_string($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "$label must be a valid email address.");
        }

        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) < $min) {
            $this->addError($field, "$label must be at least $min characters.");
        }

        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->addError($field, "$label must be at most $max characters.");
        }

        return $this;
    }

    public function matches(string $field, string $otherField, string $label): self
    {
        if (($this->data[$field] ?? null) !== ($this->data[$otherField] ?? null)) {
            $this->addError($field, "$label does not match.");
        }

        return $this;
    }

    public function accepted(string $field, string $label): self
    {
        if (!filter_var($this->data[$field] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->addError($field, "$label must be accepted.");
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }

    private function addError(string $field, string $message): void
    {
        // Keep only the first error per field.
        $this->errors[$field] ??= $message;
    }
}
