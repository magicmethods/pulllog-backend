<?php

namespace App\Http\Controllers\Api\Currencies;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController extends Controller
{
    /**
     * GET /currencies
     *
     * Optional:
     *   - ?codes=USD,JPY,EUR で通貨コードの部分取得
     *   - HTTPキャッシュ: If-None-Match / If-Modified-Since 対応（24h）
     */
    public function index(Request $request): JsonResponse
    {
        // 任意の部分取得（例: /currencies?codes=USD,JPY）
        $codes = $request->query('codes');
        $codeList = null;
        if ($codes !== null) {
            $arr = is_array($codes) ? $codes : explode(',', (string)$codes);
            $codeList = array_values(array_filter(array_map(
                static fn($c) => strtoupper(trim((string)$c)),
                $arr
            )));
        }

        // 対象集合のメタ（ETag/Last-Modified 用）
        // ※ フィルタ時は集合が変わるので ETag に "codesハッシュ" を混ぜる
        $meta = Currency::when($codeList, fn($q) => $q->whereIn('code', $codeList))
            ->selectRaw('COUNT(*) AS cnt, MAX(updated_at) AS last')
            ->first();

        $lastUpdated = optional($meta?->last);
        $lastHttp = $lastUpdated ? $lastUpdated->toRfc7231String() : null;
        $codesSig = $codeList ? sha1(implode(',', $codeList)) : 'ALL';
        $etag = sha1(($lastUpdated?->timestamp ?? 0) . ':' . ($meta?->cnt ?? 0) . ':' . $codesSig);

        // 条件付きGET: 304 Not Modified
        $ifNoneMatch = $request->headers->get('if-none-match');
        $ifModifiedSince = $request->headers->get('if-modified-since');

        $cacheHeaders = [
            'ETag'          => $etag,
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
        ];
        if ($lastHttp) {
            $cacheHeaders['Last-Modified'] = $lastHttp;
        }

        $ifModOk = $ifModifiedSince && $lastHttp && (strtotime($ifModifiedSince) >= strtotime($lastHttp));
        if ($ifNoneMatch === $etag || $ifModOk) {
            return response()
                ->json(null, Response::HTTP_NOT_MODIFIED, $cacheHeaders);
        }

        // 実データ取得
        $currencies = Currency::when($codeList, fn($q) => $q->whereIn('code', $codeList))
            ->orderBy('code')
            ->get([
                'code',
                'name',
                'symbol',
                'symbol_native',
                'minor_unit',
                'rounding',
                'name_plural',
            ]);

        return response()->json(
            [
                'status' => 'success',
                'data'   => $currencies,
            ],
            Response::HTTP_OK,
            $cacheHeaders
        );
    }

    // 必要になったら単体取得も用意できます
    // public function show(string $code): JsonResponse { ... }
}
