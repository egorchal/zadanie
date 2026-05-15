<?php
/**
 * Возвращает базовый URL вебхука, чтобы подставлять разные методы REST API
 */
function getBitrix24RestUrl(string $method): string
{
    $webhookUrl = $_ENV['BITRIX24_WEBHOOKTASK'];
    $parsed = parse_url($webhookUrl);
    // Убираем последний сегмент с методом (crm.lead.add.json)
    $basePath = preg_replace('#/[^/]+\.json$#', '', $parsed['path']);
    return $parsed['scheme'] . '://' . $parsed['host'] . $basePath . '/' . $method . '.json';
}

/**
 * Логирование запросов к API
 */
function logRequest(string $type, string $url, $params = null, $response = null, ?string $error = null): void
{
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $file = $type === 'bitrix' ? 'log_bitrix.log' : 'log_public_api.log';
    $date = date('Y-m-d H:i:s');
    $entry = "[$date] " . ($error ? "ERROR: $error" : "SUCCESS") . PHP_EOL;
    $entry .= "URL: $url" . PHP_EOL;
    if ($params) {
        $entry .= "Request: " . (is_array($params) ? json_encode($params, JSON_UNESCAPED_UNICODE) : $params) . PHP_EOL;
    }
    if ($response) {
        $entry .= "Response: " . (is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : $response) . PHP_EOL;
    }
    $entry .= str_repeat('-', 50) . PHP_EOL;
    file_put_contents($logDir . '/' . $file, $entry, FILE_APPEND);
}

/**
 * Получение и кеширование курсов валют (публичное API ЦБ РФ)
 */
function getCurrencyRates(): ?array
{
    $ratesFile = __DIR__ . '/data/rates.json';
    $cbrUrl = 'https://www.cbr-xml-daily.ru/daily_json.js';

    // Проверка актуальности кеша (24 часа)
    if (file_exists($ratesFile)) {
        $cached = json_decode(file_get_contents($ratesFile), true);
        if (isset($cached['Date']) && (time() - strtotime($cached['Date']) < 86400)) {
            return $cached;
        }
    }

    // Запрос свежих курсов
    $ch = curl_init($cbrUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Логируем запрос
    logRequest('public', $cbrUrl, null, $response, $curlError ?: ($httpCode !== 200 ? "HTTP $httpCode" : null));

    if ($httpCode === 200 && $response) {
        $cbrData = json_decode($response, true);
        if (!empty($cbrData['Valute']['USD']['Value']) && !empty($cbrData['Valute']['EUR']['Value'])) {
            $rates = [
                'USD' => $cbrData['Valute']['USD']['Value'],
                'EUR' => $cbrData['Valute']['EUR']['Value'],
                'Date' => $cbrData['Date'],
            ];
            if (!is_dir(dirname($ratesFile))) {
                mkdir(dirname($ratesFile), 0755, true);
            }
            file_put_contents($ratesFile, json_encode($rates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $rates;
        }
    }

    // Если свежие не получены, пробуем вернуть кеш
    if (file_exists($ratesFile)) {
        $cached = json_decode(file_get_contents($ratesFile), true);
        if ($cached) return $cached;
    }

    return null;
}

/**
 * Отправка запроса к Bitrix24 REST API
 */
function callBitrix24Api(string $method, array $params = []): array
{
    $url = getBitrix24RestUrl($method);
    $data = http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $data,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $error = $curlError ?: ($httpCode !== 200 ? "HTTP $httpCode" : null);
    $decoded = json_decode($response, true);
    if (!$error && isset($decoded['error'])) {
        $error = 'Bitrix24 API Error: ' . $decoded['error'] . (isset($decoded['error_description']) ? ' - ' . $decoded['error_description'] : '');
    }

    logRequest('bitrix', $url, $params, $response, $error);

    return [
        'success' => !$error,
        'data' => $decoded,
        'error' => $error
    ];
}

/**
 * Получить список полей лида
 */
function getLeadFields(): array
{
    $result = callBitrix24Api('crm.lead.fields');
    return $result['success'] ? ($result['data']['result'] ?? []) : [];
}

/**
 * Создать задачу в Битрикс24
 */
function createBitrix24Task(int $leadId, array $leadData): bool
{
    $responsibleId = (int)($_ENV['BITRIX24_RESPONSIBLE_ID'] ?? 0);
    if (!$responsibleId) {
        return false; // ответственный не задан
    }

    $title = 'Обработать лид #' . $leadId . ' (' . ($leadData['NAME'] ?? '') . ')';
    $description = "Лид создан автоматически.\n";
    $description .= "Имя: " . ($leadData['NAME'] ?? '') . "\n";
    $description .= "Телефон: " . ($leadData['PHONE'] ?? '') . "\n";
    $description .= "Email: " . ($leadData['EMAIL'] ?? '') . "\n";
    $description .= "Сумма: " . ($leadData['AMOUNT_RUB'] ?? 'не указана') . " руб.\n";
    $description .= "Источник: " . ($leadData['SOURCE'] ?? '') . "\n";
    $description .= "Комментарий: " . ($leadData['COMMENTS'] ?? '');

    $taskParams = [
        'fields' => [
            'TITLE' => $title,
            'RESPONSIBLE_ID' => $responsibleId,
            'DESCRIPTION' => $description,
            'UF_CRM_TASK' => ['L_' . $leadId], // привязка к лиду
        ]
    ];

    $result = callBitrix24Api('task.item.add', $taskParams);
    return $result['success'];
}