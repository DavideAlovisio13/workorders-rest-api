<?php

declare(strict_types=1);

namespace WorkOrders\Auth;

use WorkOrders\Support\Request;

/** Bearer API-key authentication against configured keys. */
final class TokenAuth
{
    /** @param array<string,array{client:string,role:string}> $apiKeys */
    public function __construct(private array $apiKeys)
    {
    }

    /**
     * @return array{client:string,role:string}|null  identity, or null if unauthenticated
     */
    public function authenticate(Request $req): ?array
    {
        $header = $req->header('Authorization');
        if ($header === null || !preg_match('/^Bearer\s+(.+)$/i', trim($header), $m)) {
            return null;
        }
        $key = trim($m[1]);
        foreach ($this->apiKeys as $validKey => $identity) {
            if (hash_equals((string) $validKey, $key)) {
                return $identity;
            }
        }
        return null;
    }

    public static function isWriter(?array $identity): bool
    {
        return $identity !== null && ($identity['role'] ?? '') === 'admin';
    }
}
