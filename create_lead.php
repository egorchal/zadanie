<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'function.php';

$webhookUrl = $_ENV['BITRIX24_WEBHOOK'];
$webhooktaskURL = $_ENV['BITRIX24_WEBHOOKTASK'];
$responsible = $_ENV['BITRIX24_RESPONSIBLE_ID'];
// Инициализация переменных
$rates = getCurrencyRates();
$name = '';
$telephone = '';
$email = '';
$source = '';
$comment = '';
$errors = '';
$local_id = null;
$bitrix_lead_id = null;
$response_data = null;
$form_data = [];

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST["name"] ?? '');
    $telephone = trim($_POST["telephone"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $source = trim($_POST["source"] ?? '');
    $comment = trim($_POST["comment"] ?? '');
    $amount_rub = trim($_POST["amount_rub"] ?? '');
    // Валидация полей
    if (empty($name) || empty($telephone) || empty($email) || empty($source) || empty($comment)) {
        $errors = "Заполните все поля!";
    }
    
    // Если нет ошибок валидации, отправляем в Bitrix24
    if (empty($errors)) {
        // Генерация локального ID
        $local_id = date('Ymd_His') . '_' . rand(1000, 9999);
        // Подготовка данных для отправки
        $data = http_build_query([
            
            'fields' => [
                'TITLE' => 'Новый лид от ' . $name,
                'NAME' => $name,
                'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
                'PHONE' => [['VALUE' => $telephone, 'VALUE_TYPE' => 'WORK']],
                'COMMENTS' => $comment,
                'SOURCE_ID' => $source,
                'OPPORTUNITY' =>$amount_rub,
            ]
        ]);
        
        // Отправка запроса в Bitrix24
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POSTFIELDS => $data,
        ));
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($curl)) {
            $errors = 'Ошибка CURL: ' . curl_error($curl);
        } else {
            $response_data = json_decode($response, true);
            
            if ($http_code !== 200) {
                $errors = 'HTTP ошибка: ' . $http_code;
            } elseif (isset($response_data['error'])) {
                $errors = 'Ошибка Bitrix24: ' . $response_data['error'] . 
                         (isset($response_data['error_description']) ? ' - ' . $response_data['error_description'] : '');
            } else {
                $bitrix_lead_id = $response_data['result'] ?? null;
            }
        }
        // ... после получения $bitrix_lead_id и перед записью в JSON

$taskCreated = false;
if ($bitrix_lead_id && empty($errors)) {
    $taskCreated = createBitrix24Task((int)$bitrix_lead_id, [
        'NAME' => $name,
        'PHONE' => $telephone,
        'EMAIL' => $email,
        'AMOUNT_RUB' => $amount_rub ?? null,
        'SOURCE' => $source,
        'COMMENTS' => $comment
    ]);
    $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $webhooktaskURL,
            CURLOPT_POSTFIELDS => $taskCreated,
        ));
        curl_exec($curl);
}
        curl_close($curl);
        
        
    }
    // Сохранение данных в JSON файл
        $form_data = [
            'LOCAL_ID' => $local_id,
            'BITRIX_ID' => $bitrix_lead_id,
            'NAME' => $name,
            'EMAIL' => $email,
            'PHONE' => $telephone,
            'COMMENTS' => $comment,
            'SOURCE' => $source,
            'AMOUNT_RUB'  => $amount_rub !== '' ? (float)$amount_rub : null,
            'TIMESTAMP' => date('Y-m-d H:i:s'),
            'STATUS' => empty($errors) ? 'success' : 'error',
            'ERROR' => $errors
        ];
        
        // Создаем директорию если её нет
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
        
        // Читаем существующие данные
        $filePath = 'data/bitrix_leads.json';
        $existing_data = [];
        if (file_exists($filePath)) {
            $json_content = file_get_contents($filePath);
            $existing_data = json_decode($json_content, true) ?? [];
        }
        
        // Добавляем новую запись
        $existing_data[] = $form_data;
        
        // Сохраняем обновленные данные
        file_put_contents($filePath, json_encode($existing_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title></title>
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
        <td><?php echo htmlspecialchars($local_id); ?></td>
    </tr>
    <tr>
        <td><strong>ID лида в Bitrix24:</strong></td>
        <td><?php echo $bitrix_lead_id ? htmlspecialchars($bitrix_lead_id) : 'Не получен'; ?></td>
    </tr>
    <tr>
        <td><strong>Данные формы:</strong></td>
        <td>
            <strong>Имя:</strong> <?php echo htmlspecialchars($name); ?><br>
            <strong>Телефон:</strong> <?php echo htmlspecialchars($telephone); ?><br>
            <strong>Email:</strong> <?php echo htmlspecialchars($email); ?><br>
            <strong>Источник:</strong> <?php echo htmlspecialchars($source); ?><br>
            <strong>Комментарий:</strong> <?php echo htmlspecialchars($comment); ?>
        </td>
    </tr>
    <tr>
        <td><strong>Дата отправки:</strong></td>
        <td><?php echo date('Y-m-d H:i:s'); ?></td>
    </tr>
    <tr>
        <td><strong>Результат запроса:</strong></td>
        <td>
            <?php if (empty($errors)): ?>
                <strong style="color: green;">УСПЕШНО</strong> 
                (HTTP: <?php echo isset($http_code) ? $http_code : 'N/A'; ?>)
            <?php else: ?>
                <strong style="color: red;">ОШИБКА</strong>
                (HTTP: <?php echo isset($http_code) ? $http_code : 'N/A'; ?>)
            <?php endif; ?>
        </td>
    </tr>
    <?php if (!empty($errors)): ?>
    <tr>
        <td><strong>Текст ошибки:</strong></td>
        <td style="color: red;"><?php echo htmlspecialchars($errors); ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php endif; ?>
</div>
    </div>
    </div>
</body>
</html>