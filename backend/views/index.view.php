<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<?php if ($state->getAttr('id')):?>
    <a href="https://www.youtube.com/watch?v=<?=$state->getAttr('id')?>">Live</a>
    <br/>
    <br/>
    <br/>
    <form method="post" action="/stop">
        <button>Finish</button>
    </form>
<?php else: ?>

<form method="post" action="/start">
    <input name="title" type="text">
    <button>Start</button>
</form>

<?php endif; ?>

</body>
</html>
