<?php

namespace App\Helpers;

class EmailValidator
{
    /**
     * Allowed TLDs (common, trusted top-level domains)
     */
    protected static array $allowedTlds = [
        // Generic
        'com', 'net', 'org', 'info', 'biz',
        // Country codes (common)
        'az', 'ru', 'ua', 'tr', 'de', 'uk', 'fr', 'it', 'es', 'pl', 'nl', 'be', 'at', 'ch',
        'us', 'ca', 'au', 'nz', 'jp', 'cn', 'kr', 'in', 'br', 'mx', 'ar',
        'io', 'co', 'me', 'tv', 'cc', 'ws',
        // New gTLDs (common ones)
        'edu', 'gov', 'mil',
    ];

    /**
     * Disposable/temporary email domains to block
     */
    protected static array $disposableDomains = [
        '33mail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.com',
        'throwaway.email', '10minutemail.com', 'temp-mail.org', 'fakeinbox.com',
        'trashmail.com', 'maildrop.cc', 'dispostable.com', 'sharklasers.com',
        'yopmail.com', 'getnada.com', 'mohmal.com', 'tempail.com',
    ];

    /**
     * Specific blocked domains
     */
    protected static array $blockedDomains = [
        'beta.edu.pl',
    ];

    /**
     * Keyboard patterns that indicate gibberish
     */
    protected static array $keyboardPatterns = [
        'qwert', 'werty', 'ertyu', 'rtyui', 'tyuio', 'yuiop',
        'asdf', 'sdfg', 'dfgh', 'fghj', 'ghjk', 'hjkl',  // shorter patterns
        'zxcv', 'xcvb', 'cvbn', 'vbnm',
        'qazw', 'azws', 'wsxe', 'sxed', 'edcr', 'dcrf',
        '1234', '2345', '3456', '4567', '5678', '6789', '7890',
        'abcd', 'bcde', 'cdef', 'defg', 'efgh',
    ];

    /**
     * Test-like local parts to block
     */
    protected static array $testPatterns = [
        'test', 'tester', 'testing', 'testuser', 'testaccount',
        'demo', 'sample', 'example', 'fake', 'dummy',
        'admin', 'administrator', 'root', 'user', 'guest',
        'noreply', 'no-reply', 'donotreply', 'do-not-reply',
        'null', 'void', 'none', 'nobody', 'nothing',
        'temp', 'temporary', 'tmp',
        'info', 'contact', 'support', 'sales', 'hello', 'hi',
    ];

    /**
     * Validate email address with comprehensive checks
     */
    public static function isValid(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        $email = strtolower(trim($email));

        // Basic format check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Must have @ and domain with TLD
        if (!preg_match('/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/', $email)) {
            return false;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        $localPart = $parts[0];
        $domain = $parts[1];

        // Get TLD
        $tld = substr($domain, strrpos($domain, '.') + 1);

        // Check TLD is allowed
        if (!in_array($tld, self::$allowedTlds)) {
            return false;
        }

        // Check for disposable email domains
        if (self::isDisposableDomain($domain)) {
            return false;
        }

        // Check for blocked domains
        if (in_array($domain, self::$blockedDomains)) {
            return false;
        }

        // Check local part validity
        if (!self::isValidLocalPart($localPart)) {
            return false;
        }

        // Check for invalid patterns in full email
        if (self::hasInvalidPatterns($email)) {
            return false;
        }

        return true;
    }

    /**
     * Check if domain is a disposable email service
     */
    protected static function isDisposableDomain(string $domain): bool
    {
        foreach (self::$disposableDomains as $disposable) {
            if ($domain === $disposable || str_ends_with($domain, '.' . $disposable)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate local part (before @)
     */
    protected static function isValidLocalPart(string $localPart): bool
    {
        // Too short (less than 3 chars)
        if (strlen($localPart) < 3) {
            return false;
        }

        // All same character (aaa, bbb, xxx)
        if (preg_match('/^(.)\1+$/', $localPart)) {
            return false;
        }

        // Mostly same character (aaab, aaaa1)
        $chars = count_chars($localPart, 1);
        $maxCount = max($chars);
        if ($maxCount >= strlen($localPart) * 0.7 && strlen($localPart) >= 4) {
            return false;
        }

        // Keyboard mashing patterns
        foreach (self::$keyboardPatterns as $pattern) {
            if (str_contains($localPart, $pattern)) {
                return false;
            }
        }

        // Test-like patterns (exact match or with numbers)
        $localPartClean = preg_replace('/[0-9]+/', '', $localPart);
        foreach (self::$testPatterns as $testPattern) {
            if ($localPartClean === $testPattern) {
                return false;
            }
        }

        // Random gibberish detection: too many consonants in a row (5+)
        if (preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $localPart)) {
            return false;
        }

        // No vowels at all in a long string (likely gibberish)
        if (strlen($localPart) >= 6 && !preg_match('/[aeiouy]/i', $localPart)) {
            return false;
        }

        return true;
    }

    /**
     * Check for invalid patterns in full email
     */
    protected static function hasInvalidPatterns(string $email): bool
    {
        $invalidPatterns = [
            '/\.{2,}/',            // consecutive dots
            '/@.*@/',              // multiple @ signs
            '/\s/',                // whitespace
        ];

        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize email (lowercase, trim)
     */
    public static function normalize(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }
        return strtolower(trim($email));
    }

    /**
     * Get validation error reason
     */
    public static function getError(?string $email): ?string
    {
        if (empty($email)) {
            return 'Email is empty';
        }

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }

        if (!preg_match('/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/', $email)) {
            return 'Email missing valid domain';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return 'Invalid email format';
        }

        $localPart = $parts[0];
        $domain = $parts[1];
        $tld = substr($domain, strrpos($domain, '.') + 1);

        if (!in_array($tld, self::$allowedTlds)) {
            return "TLD '.{$tld}' is not allowed";
        }

        if (self::isDisposableDomain($domain)) {
            return 'Disposable email addresses are not allowed';
        }

        if (in_array($domain, self::$blockedDomains)) {
            return 'This email domain is blocked';
        }

        if (strlen($localPart) < 3) {
            return 'Email local part is too short';
        }

        if (preg_match('/^(.)\1+$/', $localPart)) {
            return 'Email appears to be fake (repeating characters)';
        }

        foreach (self::$keyboardPatterns as $pattern) {
            if (str_contains($localPart, $pattern)) {
                return 'Email appears to be fake (keyboard pattern)';
            }
        }

        $localPartClean = preg_replace('/[0-9]+/', '', $localPart);
        foreach (self::$testPatterns as $testPattern) {
            if ($localPartClean === $testPattern) {
                return 'Test/generic email addresses are not allowed';
            }
        }

        if (preg_match('/[bcdfghjklmnpqrstvwxz]{5,}/i', $localPart)) {
            return 'Email appears to be gibberish';
        }

        if (strlen($localPart) >= 6 && !preg_match('/[aeiouy]/i', $localPart)) {
            return 'Email appears to be gibberish (no vowels)';
        }

        if (self::hasInvalidPatterns($email)) {
            return 'Email contains invalid characters';
        }

        return null;
    }
}
