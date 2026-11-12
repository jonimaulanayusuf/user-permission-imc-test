<?php

declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     *
     * @return array<string, array<int, string>>
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? $field;
            $fieldRules = array_values(array_filter(
                explode('|', $ruleString),
                static fn (string $rule): bool => $rule !== '',
            ));

            $value = $data[$field] ?? null;
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            if ($isEmpty) {
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field][] = $label . ' wajib diisi.';
                }

                continue;
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

                switch ($name) {
                    case 'string':
                        if (!is_string($value)) {
                            $errors[$field][] = $label . ' harus berupa teks.';
                        }
                        break;

                    case 'integer':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $errors[$field][] = $label . ' harus berupa angka bulat.';
                        }
                        break;

                    case 'boolean':
                        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
                            $errors[$field][] = $label . ' harus berupa nilai aktif/nonaktif.';
                        }
                        break;

                    case 'email':
                        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                            $errors[$field][] = $label . ' harus berupa email yang valid.';
                        }
                        break;

                    case 'min':
                        $min = (int) $argument;
                        if (is_string($value) && mb_strlen($value) < $min) {
                            $errors[$field][] = $label . ' minimal ' . $min . ' karakter.';
                        } elseif (!is_string($value) && is_numeric($value) && (float) $value < $min) {
                            $errors[$field][] = $label . ' minimal ' . $min . '.';
                        }
                        break;

                    case 'max':
                        $max = (int) $argument;
                        if (is_string($value) && mb_strlen($value) > $max) {
                            $errors[$field][] = $label . ' maksimal ' . $max . ' karakter.';
                        } elseif (!is_string($value) && is_numeric($value) && (float) $value > $max) {
                            $errors[$field][] = $label . ' maksimal ' . $max . '.';
                        }
                        break;

                    case 'regex':
                        if (!is_string($value) || $argument === null || @preg_match($argument, $value) !== 1) {
                            $errors[$field][] = $label . ' formatnya tidak valid.';
                        }
                        break;

                    case 'in':
                        $allowed = $argument === null ? [] : explode(',', $argument);
                        if (!in_array((string) $value, $allowed, true)) {
                            $errors[$field][] = $label . ' tidak valid.';
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}
