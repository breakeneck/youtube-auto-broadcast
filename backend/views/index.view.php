<?php /**
* @var \App\Row[] $lastRows
 */
?>
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>ІССКОН Луцьк: пряма трансляція</title>


    <link rel="apple-touch-icon" sizes="180x180" href="/ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/ico/favicon-16x16.png">
    <link rel="manifest" href="/ico/site.webmanifest">
    <link rel="mask-icon" href="/ico/safari-pinned-tab.svg" color="#5bbad5">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">
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
    /*input {width: 100%;}*/
    /*.btn-primary {float: right;}*/
    /*.spinner-border {display: none;}*/
    .flex {display: flex;}
    .flex input {flex: 1;}
</style>

<?php if ($state->getAttr('id')):?>
    <br/>
    <br/>
    <br/>
    <form method="post" action="/stop">
        <a class="icon-link" href="https://www.youtube.com/watch?v=<?=$state->getAttr('id')?>">
            <i class="bi bi-youtube"></i>
            Youtube
        </a>
        <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
            Завершити трансляцію
        </button>
    </form>
<?php else: ?>
    <div>Сьогодні <?= \App\Utils::getLocalTimeStr('now', 'EEEE dd.MM.Y')?></div>
    <table class="table table-striped table-hover">
        <?php foreach ($lastRows as $row):?>
        <tr>
            <?php if (! $row->verse) continue ?>
            <td><?=$row->dateFormatted()?></td>
            <td><?=$row->dayOfWeek()?></td>
            <td><?=$row->verse?></td>
            <td><?=$row->username?></td>
            <?php if (isset($row->title)):?>
                <?php if (strlen($row->title) > 5):?>
                <td>
<!--                    <a href="#" onclick="document.getElementById('input').value = '<?php //=$row['4'] ?? 'empty'?>//'; return false;"> Start </a>-->
                    <button type="button" class="go btn btn-success"
                            data-title="<?=$row->title?>"
                            data-verse="<?=$row->verse?>"
                            data-book="<?=$row->book?>"
                    >
                        <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
                        Go
                    </button>
                </td>
                <?php endif ?>
            <?php endif ?>
        </tr>
        <?php endforeach; ?>
    </table>
    <form id="form" method="post" action="/start">
    <br/>
    <div class="flex">
        <input id="book" name="book" type="hidden">
        <input id="verse" name="verse" type="hidden">
        <input id="title" name="title" type="text">
        <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm visually-hidden" role="status" aria-hidden="true"></span>
            Go
        </button>
    </div>
</form>

<?php endif; ?>
<script type="text/javascript">
    $(document).on('click', '.go', function () {
        $('#title').val($(this).attr('data-title'));
        $('#verse').val($(this).attr('data-verse'));
        $('#book').val($(this).attr('data-book'));

        $('#form').submit();
    })
    $('form').submit(() => {
        $('button').prop('disabled', true);
        $('.spinner-border').removeClass('visually-hidden');
        return true;
    })
</script>
</body>
</html>
