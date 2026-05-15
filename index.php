<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/navigation.css">
</head>
<?php require_once("blocks/header.php");?>
<body>  
    <div class="feedback">          
        <div class="container">
            <form action="create_lead.php" method="post">
                <h1>Создать лид</h1>
                <div class="">
                    <div>
                        <label>Имя</label>
                        <input type="name" name="name">
                    </div>
                    <div>
                        <label>Телефон</label>
                        <input type="telephone" name="telephone">
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" >
                    </div>
                    <div>
                        <label>Источник</label>
                        <input type="source" name="source">
                    </div>
                    <div>
                        <label>Коментарий</label>
                        <input type="comment" name="comment">
                    </div>
                    <div>
                        <label>Сумма в рублях (необязательно)</label>
                        <input type="number" name="amount_rub" placeholder="10000.00" >
                    </div>
                </div>
                <button type="submit">Создать лид</button>
            </form>
        </div>
    </div>

</body>
</html>