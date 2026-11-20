<?php

declare(strict_types=1);

namespace App\Support;

final class Validation
{
    public static function required(array $input, array $fields): array
    {
        $errors = [];

        foreach ($fields as $field => $label) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = "{$label} სავალდებულოა.";
            }
        }

        return $errors;
    }

    public static function email(string $value, string $field = 'email'): array
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL)
            ? []
            : [$field => 'ელფოსტის ფორმატი არასწორია.'];
    }
}

