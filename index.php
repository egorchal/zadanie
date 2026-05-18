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

$leadSources = getLeadSources();
$sources = $leadSources;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<body>
    <?php require_once("blocks/header.php"); ?>
    <div class="feedback">          
        <div class="container">
            <form action="create_lead.php" method="post">
                <h1>Создать лид</h1>
                <div class="">
                    <div>
                        <label>Имя</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label>Телефон</label>
                        <input type="telephone" name="telephone" placeholder="+7 (___) ___-__-__">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div>
                        <label>Источник</label>
                        <select name="source" required>
                            <?php foreach ($sources as $source): ?>
                                <?php
                                $sourceId = $source['STATUS_ID'] ?? $source['ID'] ?? '';
                                $sourceName = $source['NAME'] ?? $source['TITLE'] ?? $sourceId;
                                ?>
                                <option value="<?= h($sourceId) ?>"><?= h($sourceName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Комментарий</label>
                        <input type="text" name="comment" required>
                    </div>
                    <div>
                        <label>Сумма в рублях (необязательно)</label>
                        <input type="number" name="amount_rub" placeholder="10000.00" step="0.01" min="0">
                    </div>
                </div>
                <button type="submit">Создать лид</button>
            </form>
        </div>
    </div>

</body>
</html>
