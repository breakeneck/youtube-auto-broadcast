<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script
            src="https://code.jquery.com/jquery-3.7.1.slim.min.js"
            integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8="
            crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <title>ІССКОН Луцьк: пряма трансляція</title>
</head>
<body>

<style>
    @media (max-width: 600px) {
        body {width: 360px;}
    }
    @media (min-width: 601px) {
        body {width: 500px;}
    }
    body {margin: 0 auto;}
    /*table, th, td {*/
    /*    border: 1px solid black;*/
    /*    border-collapse: collapse;*/
    /*}*/
    td {padding: 5px 10px;}
    input {width: 100%;}
    button {float: right;}
</style>

<?php if ($state->getAttr('id')):?>
    <a href="https://www.youtube.com/watch?v=<?=$state->getAttr('id')?>">Live</a>
    <br/>
    <br/>
    <br/>
    <form method="post" action="/stop">
        <button>Завершити</button>
    </form>
<?php else: ?>

    <table class="table table-striped table-hover">
        <?php foreach ($lastRows as $row):?>
        <tr>
            <?php foreach ($row as $n => $item):?>
            <?php if (in_array($n, [2, 3])) continue ?>
            <td><?=$item?></td>
            <?php endforeach; ?>
            <?php if (isset($row[4])):?>
                <?php if (strlen($row[4]) > 5):?>
                <td>
<!--                    <a href="#" onclick="document.getElementById('input').value = '<?php //=$row['4'] ?? 'empty'?>//'; return false;"> Start </a>-->
                    <button type="button" class="go btn btn-success" ref="<?=$row[4]?>">Go</button>
                </td>
                <?php endif ?>
            <?php endif ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <form id="form" method="post" action="/start">
    <br/>
    <input class="form-control" id="input" name="title" type="text">
    <br/>
    <button type="submit" class="btn btn-primary">Почати трансляцію</button>
</form>

<?php endif; ?>
<script type="text/javascript">
    $(document).on('click', '.go', function () {
        $('#input').val($(this).attr('ref'));
        $('#form').submit();
    })
</script>
</body>
</html>
