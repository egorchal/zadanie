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

$fields = getLeadFields();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Поля лида Bitrix24</title>
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
<div class="feedback">
    <p><a href="index.php">← Назад к форме</a> | <a href="report.php">Отчёт</a></p>
    <div class="container">
        <h2>Список полей лида (crm.lead.fields)</h2>
        <?php if (empty($fields)): ?>
            <p>Не удалось получить поля. Проверьте логи <code>data/log_bitrix.log</code></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Тип</th>
                        <th>Обязательное</th>
                        <th>Только для чтения</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $fieldId => $field): ?>
                    <tr>
                        <td><?= h($fieldId) ?></td>
                        <td><?= h($field['title'] ?? '') ?></td>
                        <td><?= h($field['type'] ?? '') ?></td>
                        <td><?= !empty($field['isRequired']) ? 'Да' : 'Нет' ?></td>
                        <td><?= !empty($field['isReadOnly']) ? 'Да' : 'Нет' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
