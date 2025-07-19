<?php

// Hook for `POST /logs/import/:id`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // Extract dynamic parameters
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $appId = isset($dynamicParam2) ? $dynamicParam2 : null;
    $mode = isset($request_data['query_params']['mode']) ? $request_data['query_params']['mode'] : null;// 'overwrite'

    // ファイル取得チェック
    if (!$request_data['files'] || empty($request_data['files']) || !isset($request_data['files']['file']) || $request_data['files']['file']['size'] === 0) {
        returnErrorWithState('Failed to retrieve uploaded file.');
    }
    $uploadFile = $request_data['files']['file'];
    $uploadFileType = $uploadFile['type'] ?? '';
    if (!in_array($uploadFileType, ['text/csv', 'application/json'], true)) {
        returnErrorWithState('Invalid file format.');
    }
    if ($uploadFile['error'] !== 0 || !is_uploaded_file($uploadFile['tmp_name'])) {
        returnErrorWithState('Failed to upload file.');
    }
    if (!$mode) {
        returnErrorWithState('No import mode specified. Use "overwrite" or "merge".');
    }
    // ファイルパース
    $parsedLogs = [];
    $nowISOString = (new DateTime('now', new DateTimeZone('UTC')))->format("Y-m-d\TH:i:s\Z");
    try {
        if ($uploadFileType === 'text/csv') {
            // CSVパース
            $parsedLogs = parseAndFormatCsv($uploadFile['tmp_name'], $appId ?? '', $nowISOString);
        } else {
            // JSONパース
            $parsedLogs = parseAndFormatJson($uploadFile['tmp_name'], $nowISOString);
        }
    } catch (Exception $ex) {
        returnErrorWithState('Failed to parse uploaded file: ' . $ex->getMessage());
    }
    // $checksum = crc32(json_encode($parsedLogs));
    //dump([$appId, $uploadFile, $mode, count($parsedLogs), $checksum, $parsedLogs[0]]);
    // 履歴インポート処理（モックはファイル管理なので暫定処理）
    $appLogsFile = './responses/logs/'. $appId .'.json';
    // 保存処理
    try {
        if (file_exists($appLogsFile)) {
            if ($mode === 'overwrite') {
                // 完全上書き（既存削除後に新規登録）
                saveLogsFile($appLogsFile, $parsedLogs);
            } else {
                // 差分マージ（既存の重複履歴は上書き）
                $prevLogs = readJsonArray($appLogsFile);
                $mergedLogs = mergeLogs($prevLogs, $parsedLogs);
                saveLogsFile($appLogsFile, $mergedLogs);
            }
        } else {
            // 既存ログが無ければアップロードされたログデータで新規登録
            saveLogsFile($appLogsFile, $parsedLogs);
        }
    } catch (Exception $ex) {
        returnErrorWithState('File save failed: ' . $ex->getMessage());
    }
    returnResponse([
        'state' => 'success',
    ]);
}

// ---------- Utility functions ----------

function returnErrorWithState(string $message): void {
    returnResponse([
        'state' => 'error',
        'message' => $message,
    ]);
}

/**
 * JSONファイルを配列で安全に読み込む
 */
function readJsonArray(string $filePath): array {
    if (!file_exists($filePath)) return [];
    $content = @file_get_contents($filePath);
    if ($content === false) return [];
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * ログ配列をファイル保存
 */
function saveLogsFile(string $filePath, array $logs): void {
    if (false === @file_put_contents($filePath, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        throw new Exception('File write failed: ' . $filePath);
    }
}

/**
 * CSVファイルをパース＆ログ配列に整形
 */
function parseAndFormatCsv(string $csvFilePath, string $appId, string $nowISOString): array {
    if (empty($appId)) {
        throw new Exception('No appId provided');
    }
    $data = csvToArrayUtf8($csvFilePath);
    if (!is_array($data)) {
        throw new Exception('CSV read error');
    }
    foreach ($data as &$logItem) {
        $logItem['total_pulls'] = (int)($logItem['totalPulls'] ?? 0);
        $logItem['discharge_items'] = (int)($logItem['dischargeItems'] ?? 0);
        $logItem['expense'] = (float)($logItem['expense'] ?? 0);
        $logItem['free_text'] = (string)($logItem['freeText'] ?? '');
        $logItem['drop_details'] = json_decode($logItem['dropDetails'] ?? '[]', true);
        $logItem['tags'] = json_decode($logItem['tags'] ?? '[]', true);
        $logItem['appId'] = $appId;
        $logItem['images'] = [];
        $logItem['tasks'] = [];
        $logItem['last_updated'] = $nowISOString;
        $logItem['date'] = $logItem['date'] ?? ''; // 必須
        unset($logItem['totalPulls'], $logItem['dischargeItems'], $logItem['dropDetails'], $logItem['freeText']);
        ksort($logItem);
    }
    unset($logItem);
    return $data;
}

/**
 * JSONファイルをパース＆更新日時だけ補完
 */
function parseAndFormatJson(string $jsonFilePath, string $nowISOString): array {
    $raw = file_get_contents($jsonFilePath);
    $arr = json_decode($raw, true);
    if (!is_array($arr)) {
        throw new Exception('JSON parse error');
    }
    foreach ($arr as &$item) {
        $item['last_updated'] = $nowISOString;
        ksort($item);
    }
    unset($item);
    return $arr;
}

/**
 * CSVを配列でUTF-8前提で読み込む（BOM自動除去）
 */
function csvToArrayUtf8(string $csvFilePath, string $delimiter = ',', string $enclosure = '"'): array {
    $header = null;
    $data = [];
    // ファイルをUTF-8で読み込み
    $lines = @file($csvFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return [];
    // BOM除去
    if (isset($lines[0]) && strncmp($lines[0], "\xEF\xBB\xBF", 3) === 0) {
        $lines[0] = substr($lines[0], 3);
    }
    $stream = fopen('php://temp', 'r+');
    foreach ($lines as $line) fwrite($stream, $line . "\n");
    rewind($stream);

    while (($row = fgetcsv($stream, 0, $delimiter, $enclosure)) !== false) {
        if ($header === null) {
            $header = $row;
        } else {
            $data[] = array_combine($header, $row);
        }
    }
    fclose($stream);
    return $data;
}

/**
 * 差分マージ
 */
function mergeLogs(array $baseLogs, array $importLogs): array {
    $map = [];
    // 既存→マップ化
    foreach ($baseLogs as $item) {
        $date = $item['date'] ?? null;
        if ($date) $map[$date] = $item;
    }
    // 新規→上書き
    foreach ($importLogs as $item) {
        $date = $item['date'] ?? null;
        if ($date) $map[$date] = $item;
    }
    // 日付昇順ソート
    uasort($map, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    return array_values($map);
}
