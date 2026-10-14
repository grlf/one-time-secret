<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Secrets are encrypted in the browser (resources/js/app.js) with a key that
 * only ever lives in the link's #fragment. This controller stores and hands
 * back ciphertext it has no way to read.
 */
class Secret extends Controller
{
    public const ID_PATTERN = '[A-Za-z0-9]{24}';

    /** Lifetimes offered on the form, in seconds: 1 hour, 4 hours, 24 hours, 1 week. */
    public const LIFETIMES = [3600, 14400, 86400, 604800];

    /** Longest secret the form accepts, in characters. */
    public const MAX_LENGTH = 20000;

    private const ID_LENGTH = 24;

    public function create(): View
    {
        return view('secret.create', ['maxLength' => self::MAX_LENGTH]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ciphertext' => ['required', 'string', 'max:'.self::maxCiphertextLength(), 'regex:/^[A-Za-z0-9_-]+$/'],
            'expires' => ['required', 'integer', Rule::in(self::LIFETIMES)],
        ]);

        $id = Str::random(self::ID_LENGTH);

        // Also encrypted with APP_KEY, so a copy of the Redis data is useless
        // even to someone who has the link id and the #fragment key.
        Redis::set(
            self::redisKey($id),
            Crypt::encryptString($validated['ciphertext']),
            'EX',
            (int) $validated['expires'],
        );

        return response()->json(['id' => $id], 201);
    }

    // The page doesn't include the id: the script reads it from the URL, so a
    // third-party stylesheet can't pick it out of a DOM attribute.
    public function show(): View
    {
        return view('secret.show');
    }

    public function get(Request $request): JsonResponse
    {
        $id = $request->validate([
            'id' => ['required', 'string', 'regex:/^'.self::ID_PATTERN.'$/'],
        ])['id'];

        // GET and DEL in one MULTI/EXEC, so two requests racing for the same
        // link can't both receive the secret.
        [$stored] = Redis::transaction(function ($redis) use ($id) {
            $redis->get(self::redisKey($id));
            $redis->del(self::redisKey($id));
        });

        if (is_string($stored)) {
            try {
                return response()->json(['ciphertext' => Crypt::decryptString($stored)]);
            } catch (DecryptException) {
                // Stored under a different APP_KEY: unreadable, and already deleted.
            }
        }

        return response()->json([
            'message' => 'Your secret cannot be found. Please ask the sender to try again.',
        ], 404);
    }

    private static function redisKey(string $id): string
    {
        return 'secret:'.$id;
    }

    /**
     * The browser sends base64url(12-byte IV + AES-GCM ciphertext + 16-byte
     * tag), and each character of the secret is at most 3 bytes of UTF-8.
     */
    private static function maxCiphertextLength(): int
    {
        return (int) ceil((12 + self::MAX_LENGTH * 3 + 16) * 4 / 3);
    }
}
