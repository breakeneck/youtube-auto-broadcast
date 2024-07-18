<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ІССКОН Луцьк: пряма трансляція</title>
</head>
<body>

<?php if ($state->getAttr('id')):?>
    <a href="https://www.youtube.com/watch?v=<?=$state->getAttr('id')?>">Live</a>
    <br/>
    <br/>
    <br/>
    <form method="post" action="/stop">
        <button>Завершити</button>
    </form>
<?php else: ?>

<form method="post" action="/start">
    <table>
        <?php foreach ($lastRows as $row):?>
        <tr>
            <?php foreach ($row as $item):?>
            <td><?=$item?></td>
            <?php endforeach; ?>
            <?php if (isset($row['4'])):?>
                <?php if (strlen($row['4']) > 5):?>
                <td> <a href="#" onclick="document.getElementById('input').value = '<?=$row['4'] ?? 'empty'?>'; return false;"> Use </a> </td>
                <?php endif ?>
            <?php endif ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <br/>
    <br/>
    <input id="input" name="title" type="text" style="width: 300px;">
    <button>Почати трансляцію</button>
</form>

<?php endif; ?>

</body>
</html>
