<?php

namespace App\Services\Gallery;

use App\Models\App;
use App\Models\GalleryUploadTicket;
use App\Models\Log;
use App\Models\User;
use App\Services\PlanLimitService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class GalleryUploadTicketService
{
    private const RATE_LIMIT_MAX_ATTEMPTS = 10;
    private const RATE_LIMIT_DECAY_SECONDS = 60;

    public function __construct(
        private readonly PlanLimitService $planLimitService
    ) {
    }

    /**
     * @param array{
     *     file_name?: string|null,
     *     expected_bytes?: int|null,
     *     mime?: string|null,
     *     visibility?: string|null,
     *     log_id?: int|null,
     *     tags?: array<int,string>|null,
     *     app_key?: string|null,
     * } $payload
     */
    public function issue(User $user, array $payload): array
    {
        $this->guardRateLimit($user->id);

        $limits = $this->planLimitService->getGalleryLimitsForUser($user->id);
        $allowedMimes = config('gallery.allowed_mimes', []);
        $ttlSeconds = max(1, (int) config('gallery.upload_ticket_ttl', 60));

        $maxGalleryBytes = (int) $limits['max_gallery_bytes'];
        $maxUploadBytes = (int) $limits['max_upload_bytes_per_file'];

        $usageRow = DB::table('gallery_usage_stats')
            ->select('bytes_used')
            ->where('user_id', $user->id)
            ->first();

        $usedBytes = (int) ($usageRow->bytes_used ?? 0);
        $remainingBytes = max(0, $maxGalleryBytes - $usedBytes);
        $maxBytes = max(0, min($maxUploadBytes, $remainingBytes));

        if ($maxBytes <= 0) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Storage quota exceeded',
                    'usedBytes' => $usedBytes,
                    'maxBytes' => $maxGalleryBytes,
                ], 403)
            );
        }

        $expectedBytes = $payload['expected_bytes'] ?? null;
        if ($expectedBytes !== null && $expectedBytes > $maxBytes) {
            $this->throwValidationError([
                'expectedBytes' => ['Expected size exceeds available quota.'],
            ]);
        }

        $mime = $payload['mime'] ?? null;
        if ($mime !== null && !in_array($mime, $allowedMimes, true)) {
            $this->throwValidationError([
                'mime' => ['MIME type is not allowed.'],
            ]);
        }

        $visibility = $payload['visibility'] ?? 'private';

        $logId = $payload['log_id'] ?? null;
        $log = null;
        if ($logId !== null) {
            $log = $this->findLogForUser($logId, $user->id);
            if (!$log) {
                $this->throwValidationError([
                    'logId' => ['Log is not available for this user.'],
                ]);
            }
        }

        $appKey = $payload['app_key'] ?? null;
        $appId = null;
        if ($appKey !== null) {
            $app = App::where('app_key', $appKey)->first();
            if (!$app) {
                $this->throwValidationError([
                    'appKey' => ['App key is invalid.'],
                ]);
            }

            if (!$this->appBelongsToUser($app->id, $user->id)) {
                $this->throwValidationError([
                    'appKey' => ['App is not available for this user.'],
                ]);
            }

            $appId = (int) $app->id;
        }

        if ($log !== null && $log->app_id !== null) {
            $logAppId = (int) $log->app_id;
            if ($appId !== null && $appId !== $logAppId) {
                $this->throwValidationError([
                    'appKey' => ['App key does not match the log.'],
                ]);
            }
            $appId = $logAppId;
        }

        $token = Str::random(64);
        $expiresAt = CarbonImmutable::now()->addSeconds($ttlSeconds);

        $meta = [];
        if ($visibility !== null) {
            $meta['visibility'] = $visibility;
        }
        if ($logId !== null) {
            $meta['logId'] = $logId;
        }
        if (!empty($payload['tags'])) {
            $meta['tags'] = $payload['tags'];
        }
        if ($appKey !== null) {
            $meta['appKey'] = $appKey;
        }

        $ticket = GalleryUploadTicket::create([
            'user_id' => $user->id,
            'token' => $token,
            'app_id' => $appId,
            'file_name' => $payload['file_name'] ?? null,
            'expected_bytes' => $expectedBytes,
            'mime' => $mime,
            'max_bytes' => $maxBytes,
            'visibility' => $visibility,
            'log_id' => $logId,
            'tags' => $payload['tags'] ?? null,
            'meta' => $meta ?: null,
            'expires_at' => $expiresAt,
        ]);

        return [
            'uploadUrl' => route('gallery.assets.store'),
            'token' => $ticket->token,
            'expiresAt' => $ticket->expires_at->toIso8601String(),
            'maxBytes' => $ticket->max_bytes,
            'allowedMimeTypes' => $allowedMimes,
            'headers' => [
                'x-upload-token' => $ticket->token,
            ],
            'meta' => $meta ?: new \stdClass(),
            'appId' => $appId,
        ];
    }

    public function findForUserOrFail(int $userId, string $token): GalleryUploadTicket
    {
        $ticket = GalleryUploadTicket::where('token', $token)->first();
        if (!$ticket || (int) $ticket->user_id !== (int) $userId) {
            throw new HttpResponseException(
                response()->json(['message' => 'Invalid upload token'], 401)
            );
        }

        if ($ticket->used_at !== null) {
            throw new HttpResponseException(
                response()->json(['message' => 'Upload token already used'], 401)
            );
        }

        if ($ticket->expires_at->isPast()) {
            throw new HttpResponseException(
                response()->json(['message' => 'Upload token expired'], 401)
            );
        }

        return $ticket;
    }

    public function markAsUsed(GalleryUploadTicket $ticket): void
    {
        $updated = GalleryUploadTicket::where('id', $ticket->id)
            ->whereNull('used_at')
            ->update(['used_at' => CarbonImmutable::now()]);

        if ($updated === 0) {
            throw new HttpResponseException(
                response()->json(['message' => 'Upload token already used'], 401)
            );
        }
    }

    private function guardRateLimit(int $userId): void
    {
        $key = sprintf('gallery-upload-ticket:%d', $userId);

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new HttpResponseException(
                response()->json([
                    'message' => 'Too many upload ticket requests. Try again later.',
                    'retryAfter' => $retryAfter,
                ], 429)->withHeaders([
                    'Retry-After' => $retryAfter,
                ])
            );
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY_SECONDS);
    }

    private function findLogForUser(int $logId, int $userId): ?Log
    {
        return Log::where('id', $logId)
            ->where('user_id', $userId)
            ->first();
    }

    private function appBelongsToUser(int $appId, int $userId): bool
    {
        return DB::table('user_apps')
            ->where('user_id', $userId)
            ->where('app_id', $appId)
            ->exists();
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    private function throwValidationError(array $errors): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422)
        );
    }
}
