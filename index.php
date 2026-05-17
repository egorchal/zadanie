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
                        <select type="source" name="source" style="background: #2C2420; border-radius: 10px;border: 0.6px solid #CECECE;display: block;width: 90%; padding: 15px 10px;outline: none; color: #fff; margin-top: 7px;margin-bottom: 20px;">
                            <option value="Звонок">Звонок</option>
                            <option value="Электронная почта">Электронная почта</option>
                            <option value="Веб-сайт">Веб-сайт</option>
                            <option value="Реклама">Реклама</option>
                            <option value="Существующий клиент">Существующий клиент</option>
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
