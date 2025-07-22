<?php

namespace App\Http\Controllers\Api;

use Crell\Serde\SerdeCommon;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


use App\Http\Controllers\Api\DefaultApiInterface;
//use App\Http\Controllers\Controller;

class DefaultController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private readonly DefaultApiInterface $api,
        private readonly SerdeCommon $serde = new SerdeCommon(),
    )
    {
    }

    /**
     * Operation appsAppIdDelete
     *
     * アプリの削除
     *
     */
    public function appsAppIdDelete(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        try {
            $apiResult = $this->api->appsAppIdDelete($appId);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\DeleteResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\DeleteResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\DeleteResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation appsAppIdGet
     *
     * 単一アプリデータ取得
     *
     */
    public function appsAppIdGet(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        try {
            $apiResult = $this->api->appsAppIdGet($appId);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof \OpenAPIServerModelAppData) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\AppsAppIdGet404Response) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation appsAppIdPut
     *
     * アプリデータ更新
     *
     */
    public function appsAppIdPut(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        $appData = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\AppData::class);

        try {
            $apiResult = $this->api->appsAppIdPut($appId, $appData);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\AppData) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent404) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation appsGet
     *
     * ユーザーの登録アプリ一覧取得
     *
     */
    public function appsGet(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        try {
            $apiResult = $this->api->appsGet();
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if (is_array($apiResult)) {
            $serialized = array_map(fn ($item) => $this->serde->serialize($item, format: 'array'), $apiResult);
            return response()->json($serialized, 200);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation appsPost
     *
     * アプリ新規登録
     *
     */
    public function appsPost(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $appData = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\AppData::class);

        try {
            $apiResult = $this->api->appsPost($appData);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\AppData) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 201);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsAppIdGet
     *
     * アプリの日次ログ一覧取得
     *
     */
    public function logsAppIdGet(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
                'from' => [
                ],
                'to' => [
                ],
                'limit' => [
                    'gte:1',
                    'integer',
                ],
                'offset' => [
                    'gte:0',
                    'integer',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        $from = $request->date('from');

        $to = $request->date('to');

        $limit = $request->integer('limit');

        $offset = $request->integer('offset');

        try {
            $apiResult = $this->api->logsAppIdGet($appId, $from, $to, $limit, $offset);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if (is_array($apiResult)) {
            $serialized = array_map(fn ($item) => $this->serde->serialize($item, format: 'array'), $apiResult);
            return response()->json($serialized, 200);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent404) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsDailyAppIdDateDelete
     *
     * 日次ログデータの削除
     *
     */
    public function logsDailyAppIdDateDelete(Request $request, string $appId, \DateTime $date): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,'date' => $date,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
                'date' => [
                    'required',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }



        try {
            $apiResult = $this->api->logsDailyAppIdDateDelete($appId, $date);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\DeleteResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\DeleteResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsDailyAppIdDateGet
     *
     * 指定アプリ・日付の日次ログデータ取得
     *
     */
    public function logsDailyAppIdDateGet(Request $request, string $appId, \DateTime $date): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,'date' => $date,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
                'date' => [
                    'required',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }



        try {
            $apiResult = $this->api->logsDailyAppIdDateGet($appId, $date);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\DateLog) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\LogsDailyAppIdDateGet404Response) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsDailyAppIdDatePost
     *
     * 日次ログの新規登録
     *
     */
    public function logsDailyAppIdDatePost(Request $request, string $appId, \DateTime $date): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,'date' => $date,
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }



        $dateLog = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\DateLog::class);

        try {
            $apiResult = $this->api->logsDailyAppIdDatePost($appId, $date, $dateLog);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\DateLog) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 201);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent409) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 409);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsDailyAppIdDatePut
     *
     * 日次ログの更新
     *
     */
    public function logsDailyAppIdDatePut(Request $request, string $appId, \DateTime $date): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,'date' => $date,
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }



        $dateLog = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\DateLog::class);

        try {
            $apiResult = $this->api->logsDailyAppIdDatePut($appId, $date, $dateLog);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\DateLog) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent404) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation logsImportAppIdPost
     *
     * ログデータのバルクインポート
     *
     */
    public function logsImportAppIdPost(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
                'mode' => [
                    'required',
                ],
                'file' => [
                    'file',
                    'required',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        $mode = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\LogsImportAppIdPostModeParameter::class);

        $file = $request->file('file');

        try {
            $apiResult = $this->api->logsImportAppIdPost($appId, $mode, $file);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\ImportLogsResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation statsAppIdGet
     *
     * アプリ単位の統計データ取得
     *
     */
    public function statsAppIdGet(Request $request, string $appId): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    'appId' => $appId,
                ],
                $request->all(),
            ),
            [
                'appId' => [
                    'required',
                    'string',
                ],
                'start' => [
                    'required',
                ],
                'end' => [
                    'required',
                ],
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }


        $start = $request->date('start');

        $end = $request->date('end');

        try {
            $apiResult = $this->api->statsAppIdGet($appId, $start, $end);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\StatsData) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\NoContent400) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }

        if ($apiResult instanceof App\Models\NoContent404) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 404);
        }


        // This shouldn't happen
        abort(500);
    }
    /**
     * Operation userUpdatePut
     *
     * ユーザープロフィール更新
     *
     */
    public function userUpdatePut(Request $request): JsonResponse
    {
        $validator = Validator::make(
            array_merge(
                [
                    
                ],
                $request->all(),
            ),
            [
            ],
        );

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input'], 400);
        }

        $userUpdateRequest = $this->serde->deserialize($request->getContent(), from: 'json', to: App\Models\UserUpdateRequest::class);

        try {
            $apiResult = $this->api->userUpdatePut($userUpdateRequest);
        } catch (\Exception $exception) {
            // This shouldn't happen
            report($exception);
            return response()->json(['error' => $exception->getMessage()], 500);
        }

        if ($apiResult instanceof App\Models\UserUpdateResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 200);
        }

        if ($apiResult instanceof App\Models\UserUpdateResponse) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 400);
        }

        if ($apiResult instanceof App\Models\NoContent401) {
            return response()->json($this->serde->serialize($apiResult, format: 'array'), 401);
        }


        // This shouldn't happen
        abort(500);
    }

}
