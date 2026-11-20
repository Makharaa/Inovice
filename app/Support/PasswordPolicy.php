<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Password rules: a sensible minimum length plus a blocklist of the passwords attackers try first.
 * (Composition rules such as "one capital letter" are deliberately not used: they add little security.)
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    private const COMMON = [
        '12345678', '123456789', '1234567890', '0123456789', '987654321', '11111111', '00000000', '12341234',
        'password', 'password1', 'password12', 'password123', 'passw0rd', 'qwertyui', 'qwerty123', 'qwertyuiop',
        'asdfghjk', 'zxcvbnm1', 'abcd1234', 'abc12345', 'iloveyou', 'letmein1', 'welcome1', 'welcome123',
        'admin123', 'admin1234', 'adminadmin', 'administrator', 'changeme', 'changeme1', 'invoice123', 'makhara123',
        'parola123', 'gamarjoba', 'sakartvelo', 'tbilisi123',
    ];

    /**
     * @param list<string> $related Values the password must not be built from (e-mail, name).
     * @return string|null Error message (Georgian), or null when the password is acceptable.
     */
    public static function check(string $password, array $related = []): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'პაროლი უნდა შეიცავდეს მინიმუმ ' . self::MIN_LENGTH . ' სიმბოლოს.';
        }

        $lower = mb_strtolower($password);

        if (in_array($lower, self::COMMON, true)
            || count(array_unique(mb_str_split($lower))) === 1
            || self::isSequence($lower)) {
            return 'პაროლი ზედმეტად მარტივია. აირჩიეთ უფრო რთული, რომელსაც ადვილად ვერ მიხვდებიან.';
        }

        foreach ($related as $value) {
            $value = mb_strtolower(trim($value));
            if ($value === '') {
                continue;
            }
            $local = explode('@', $value)[0];
            if ($lower === $value || $lower === $local || (mb_strlen($local) >= 4 && str_contains($lower, $local))) {
                return 'პაროლი არ უნდა შეიცავდეს თქვენს ელფოსტას ან სახელს.';
            }
        }

        return null;
    }

    /** Straight runs such as 12345678 or abcdefgh (ascending or descending). */
    private static function isSequence(string $value): bool
    {
        $codes = array_map('mb_ord', mb_str_split($value));
        $step = $codes[1] - $codes[0];
        if (abs($step) !== 1) {
            return false;
        }
        foreach ($codes as $index => $code) {
            if ($index > 0 && $code - $codes[$index - 1] !== $step) {
                return false;
            }
        }

        return true;
    }
}
