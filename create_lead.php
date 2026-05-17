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

$name = '';
$telephone = '';
$email = '';
$source = '';
$comment = '';
$amount_rub = '';
$errors = '';
$http_code = null;
$local_id = null;
$bitrix_lead_id = null;
$response_data = null;
$task_created = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $local_id = date('Ymd_His') . '_' . random_int(1000, 9999);
    $name = trim($_POST['name'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $source = $_POST['source'];
    $comment = trim($_POST['comment'] ?? '');
    $amount_rub = trim($_POST['amount_rub'] ?? '');

    $validationErrors = [];
    if ($name === '' || $telephone === '' || $email === '' || $source === '' || $comment === '') {
        $validationErrors[] = 'Заполните все обязательные поля.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = 'Укажите корректный email.';
    }

    if ($amount_rub !== '' && (!is_numeric($amount_rub) || (float)$amount_rub < 0 || $amount_rub < 0)) {
        $validationErrors[] = 'Сумма должна быть положительным числом.';
    }

    $errors = implode(' ', $validationErrors);

    if ($errors === '') {
        $fields = [
            'TITLE' => 'Новый лид от ' . $name,
            'NAME' => $name,
            'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
            'PHONE' => [['VALUE' => $telephone, 'VALUE_TYPE' => 'WORK']],
            'COMMENTS' => $comment,
            'SOURCE_ID' => $source,
        ];

        if ($amount_rub !== '') {
            $fields['OPPORTUNITY'] = (float)$amount_rub;
        }

        $leadResult = callBitrix24Api('crm.lead.add', ['fields' => $fields]);
        $http_code = $leadResult['http_code'];
        $response_data = $leadResult['data'];

        if (!$leadResult['success']) {
            $errors = 'Ошибка Bitrix24: ' . $leadResult['error'];
        } else {
            $bitrix_lead_id = $response_data['result'] ?? null;
            if (!$bitrix_lead_id) {
                $errors = 'Bitrix24 не вернул ID лида.';
            }
        }

        if ($bitrix_lead_id && $errors === '') {
            $task_created = createBitrix24Task((int)$bitrix_lead_id, [
                'NAME' => $name,
                'PHONE' => $telephone,
                'EMAIL' => $email,
                'AMOUNT_RUB' => $amount_rub,
                'SOURCE' => $source,
                'COMMENTS' => $comment,
            ]);
        }
    }

    $form_data = [
        'LOCAL_ID' => $local_id,
        'BITRIX_ID' => $bitrix_lead_id,
        'TASK_CREATED' => $task_created,
        'NAME' => $name,
        'EMAIL' => $email,
        'PHONE' => $telephone,
        'COMMENTS' => $comment,
        'SOURCE' => $source,
        'AMOUNT_RUB' => $amount_rub !== '' ? (float)$amount_rub : null,
        'TIMESTAMP' => date('Y-m-d H:i:s'),
        'STATUS' => $errors === '' ? 'success' : 'error',
        'ERROR' => $errors,
    ];

    if (!is_dir('data')) {
        mkdir('data', 0755, true);
    }

    $filePath = 'data/bitrix_leads.json';
    $existing_data = [];
    if (file_exists($filePath)) {
        $decoded = json_decode(file_get_contents($filePath), true);
        $existing_data = is_array($decoded) ? $decoded : [];
    }

    $existing_data[] = $form_data;
    file_put_contents($filePath, json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Результат отправки</title>
    <link rel="stylesheet" href="css/create_lead.css">
</head>
<body>
    <div class="feedback">
        <p><a href="index.php">← Вернуться к форме отправки</a></p>
        <div class="container">
            <div>
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<hr>
<h2>Результат отправки:</h2>

<table border="1" cellpadding="10" cellspacing="0">
    <tr>
        <td><strong>Локальный ID:</strong></td>
        <td><?= h($local_id) ?></td>
    </tr>
    <tr>
        <td><strong>ID лида в Bitrix24:</strong></td>
        <td><?= $bitrix_lead_id ? h($bitrix_lead_id) : 'Не получен' ?></td>
    </tr>
    <tr>
        <td><strong>Задача создана:</strong></td>
        <td><?= $task_created ? 'Да' : 'Нет' ?></td>
    </tr>
    <tr>
        <td><strong>Данные формы:</strong></td>
        <td>
            <strong>Имя:</strong> <?= h($name) ?><br>
            <strong>Телефон:</strong> <?= h($telephone) ?><br>
            <strong>Email:</strong> <?= h($email) ?><br>
            <strong>Источник:</strong> <?= h($source) ?><br>
            <strong>Комментарий:</strong> <?= h($comment) ?>
        </td>
    </tr>
    <tr>
        <td><strong>Дата отправки:</strong></td>
        <td><?= h(date('Y-m-d H:i:s')) ?></td>
    </tr>
    <tr>
        <td><strong>Результат запроса:</strong></td>
        <td>
            <?php if ($errors === ''): ?>
                <strong style="color: green;">УСПЕШНО</strong>
                (HTTP: <?= $http_code !== null ? h($http_code) : 'N/A' ?>)
            <?php else: ?>
                <strong style="color: red;">ОШИБКА</strong>
                (HTTP: <?= $http_code !== null ? h($http_code) : 'N/A' ?>)
            <?php endif; ?>
        </td>
    </tr>
    <?php if ($errors !== ''): ?>
    <tr>
        <td><strong>Текст ошибки:</strong></td>
        <td style="color: red;"><?= h($errors) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php endif; ?>
</div>
    </div>
    </div>
</body>
</html>
