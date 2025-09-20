<?php

namespace App\Http\Controllers\Api\UserFilters;

use App\Enums\FilterContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserFilters\UpdateUserFilterRequest;
use App\Http\Resources\UserFilterResource;
use App\Models\UserFilter;
use App\Services\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserFilterController extends Controller
{
    public function show(Request $request, string $context): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $contextEnum = FilterContext::tryFrom($context);
        if (!$contextEnum) {
            return $this->contextNotFoundResponse($lang, $user?->id, $context);
        }

        $record = UserFilter::where('user_id', $user->id)
            ->where('context', $context)
            ->first();

        if ($record) {
            $payload = [
                'context' => $record->context,
                'version' => $record->version,
                'layout' => $record->layout ?? [],
                'filters' => $this->normalizeFilters($record->filters ?? []),
                'created' => true,
                'updatedAt' => optional($record->updated_at)->toISOString(),
            ];
        } else {
            $defaults = config("default.user_filters.defaults.$context", []);
            $payload = [
                'context' => $context,
                'version' => $defaults['version'] ?? 'v1',
                'layout' => $defaults['layout'] ?? [],
                'filters' => $this->normalizeFilters($defaults['filters'] ?? (object) []),
                'created' => false,
                'updatedAt' => null,
            ];
        }

        return $this->successResponse($request, $payload, 200);
    }

    public function update(UpdateUserFilterRequest $request, string $context): JsonResponse
    {
        $user = $request->user();
        $lang = LocaleResolver::resolve($request, $user);

        $contextEnum = FilterContext::tryFrom($context);
        if (!$contextEnum) {
            return $this->contextNotFoundResponse($lang, $user?->id, $context);
        }

        $data = $request->payload();

        $existing = UserFilter::where('user_id', $user->id)
            ->where('context', $context)
            ->first();

        if ($existing && $existing->version !== $data['version']) {
            Log::info('user_filters.version_conflict', [
                'user_id' => $user->id,
                'context' => $context,
                'stored_version' => $existing->version,
                'requested_version' => $data['version'],
            ]);

            $payload = [
                'version' => $existing->version,
                'layout' => $existing->layout ?? [],
                'filters' => $this->normalizeFilters($existing->filters ?? []),
            ];

            return $this->errorResponse([
                'state' => 'error',
                'message' => trans('messages.user_filters_version_conflict', [], $lang),
                'latestVersion' => $existing->version,
                'payload' => $payload,
            ], 409);
        }

        $userFilter = DB::transaction(function () use ($existing, $data, $user, $context) {
            if ($existing) {
                $existing->fill([
                    'version' => $data['version'],
                    'layout' => $data['layout'],
                    'filters' => $data['filters'],
                ]);
                $existing->save();

                return $existing;
            }

            return UserFilter::create([
                'user_id' => $user->id,
                'context' => $context,
                'version' => $data['version'],
                'layout' => $data['layout'],
                'filters' => $data['filters'],
            ]);
        });

        $payload = [
            'context' => $userFilter->context,
            'version' => $userFilter->version,
            'layout' => $userFilter->layout ?? [],
            'filters' => $this->normalizeFilters($userFilter->filters ?? []),
            'created' => true,
            'updatedAt' => optional($userFilter->updated_at)->toISOString(),
        ];

        $status = $userFilter->wasRecentlyCreated ? 201 : 200;

        return $this->successResponse($request, $payload, $status);
    }

    private function contextNotFoundResponse(string $lang, ?int $userId, string $context): JsonResponse
    {
        Log::info('user_filters.context_not_found', [
            'user_id' => $userId,
            'context' => $context,
        ]);

        return $this->errorResponse([
            'state' => 'error',
            'message' => trans('messages.user_filters_context_not_found', [], $lang),
        ], 404);
    }

    /**
     * @param mixed $filters
     * @return array|object
     */
    private function normalizeFilters($filters)
    {
        if (is_array($filters) && empty($filters)) {
            return (object) [];
        }

        return $filters;
    }

    private function successResponse(Request $request, array $payload, int $status): JsonResponse
    {
        $data = (new UserFilterResource($payload))->toArray($request);

        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
    }

    private function errorResponse(array $payload, int $status): JsonResponse
    {
        return new JsonResponse($payload, $status, ['Cache-Control' => 'no-store']);
    }
}