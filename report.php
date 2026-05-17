<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
require 'function.php';

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$bitrix24_domain = $_ENV['BITRIX24_WEBHOOK_LEAD'];

$rates = getCurrencyRates();

// Читаем список лидов
$leads = [];
$filePath = 'data/bitrix_leads.json';
if (file_exists($filePath)) {
    $leads = json_decode(file_get_contents($filePath), true) ?? [];
}

// Сортировка по дате (новые сверху)
usort($leads, function($a, $b) {
    return strtotime($b['TIMESTAMP'] ?? '') - strtotime($a['TIMESTAMP'] ?? '');
});

// Фильтры
$filter_source = $_GET['source'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$filtered = $leads;
if ($filter_source) {
    $filtered = array_filter($filtered, fn($l) => ($l['SOURCE'] ?? '') === $filter_source);
}
if ($search_query) {
    $filtered = array_filter($filtered, fn($l) =>
        stripos($l['NAME'] ?? '', $search_query) !== false ||
        stripos($l['PHONE'] ?? '', $search_query) !== false ||
        stripos($l['EMAIL'] ?? '', $search_query) !== false
    );
}

// Статистика
$total   = count($leads);
$success = count(array_filter($leads, fn($l) => ($l['STATUS'] ?? '') === 'success'));
$error   = $total - $success;

// Источники для фильтра
$sources = array_unique(array_column($leads, 'SOURCE'));
sort($sources);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отчёт по заявкам Bitrix24</title>
    <link rel="stylesheet" href="css/report.css">
</head>
<body>
<h1>Отчёт по заявкам</h1>
<p><a href="index.php">← Вернуться к форме</a></p>

<div class="stats">
    <div class="stat total"><h3>Всего</h3><p><?= $total ?></p></div>
    <div class="stat success"><h3>Успешно</h3><p><?= $success ?></p></div>
    <div class="stat error"><h3>Ошибок</h3><p><?= $error ?></p></div>
</div>

<?php if ($rates): ?>
    <p class="rates-info">Курсы ЦБ РФ на <?= h($rates['Date'] ?? '') ?>: USD = <?= h($rates['USD'] ?? '') ?> ₽, EUR = <?= h($rates['EUR'] ?? '') ?> ₽</p>
<?php else: ?>
    <p class="rates-error">⚠ Курсы валют недоступны. Суммы в валюте не будут рассчитаны.</p>
<?php endif; ?>

<div class="filters">
    <form method="GET">
        <select name="source">
            <option value="">Все источники</option>
            <?php foreach ($sources as $s): ?>
                <option value="<?= h($s) ?>" <?= $filter_source === $s ? 'selected' : '' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="search" placeholder="Имя, телефон или email" value="<?= h($search_query) ?>">
        <button type="submit">Применить</button>
        <a href="report.php">Сбросить</a>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>Локальный ID</th>
            <th>Bitrix24 ID</th>
            <th>Имя</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Источник</th>
            <th>Дата</th>
            <th>Сумма (₽)</th>
            <th>Сумма (USD)</th>
            <th>Сумма (EUR)</th>
            <th>Статус</th>
            <th>Ошибка</th>
            <th>Ссылка</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($filtered)): ?>
        <tr><td colspan="13" style="text-align:center;">Нет заявок</td></tr>
    <?php else: ?>
        <?php foreach ($filtered as $lead): ?>
            <?php
            $amount = $lead['AMOUNT_RUB'] ?? null;
            $usd = $eur = '';
            if ($amount !== null && $rates && !empty($rates['USD']) && !empty($rates['EUR'])) {
                $usd = round($amount / $rates['USD'], 2);
                $eur = round($amount / $rates['EUR'], 2);
            }
            ?>
            <tr class="<?= ($lead['STATUS'] ?? '') === 'error' ? 'row-error' : '' ?>">
                <td><?= h($lead['LOCAL_ID'] ?? '') ?></td>
                <td><?= !empty($lead['BITRIX_ID']) ? h($lead['BITRIX_ID']) : '—' ?></td>
                <td><?= h($lead['NAME'] ?? '') ?></td>
                <td><?= h($lead['PHONE'] ?? '') ?></td>
                <td><?= h($lead['EMAIL'] ?? '') ?></td>
                <td><?= h($lead['SOURCE'] ?? '') ?></td>
                <td><?= h($lead['TIMESTAMP'] ?? '') ?></td>
                <td><?= $amount !== null ? number_format($amount, 2, ',', ' ') . ' ₽' : '—' ?></td>
                <td><?= $usd !== '' ? '$' . number_format($usd, 2, '.', ' ') : '—' ?></td>
                <td><?= $eur !== '' ? '€' . number_format($eur, 2, '.', ' ') : '—' ?></td>
                <td><?= ($lead['STATUS'] ?? '') === 'success' ? '✅ Успешно' : '❌ Ошибка' ?></td>
                <td><?= !empty($lead['ERROR']) ? h($lead['ERROR']) : '—' ?></td>
                <td>
                    <?php if (!empty($lead['BITRIX_ID'])): ?>
                        <a href="https://<?= h($bitrix24_domain) ?>/crm/lead/details/<?= h($lead['BITRIX_ID']) ?>/" target="_blank">Открыть</a>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</body>
</html>
