<!DOCTYPE html>
<!--
ERROR:   401
MESSAGE: <?=$sMessage?>
-->
<html lang="en">
    <head>
        <title>401 Unauthorised - <?=APP_NAME?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php

        include NAILS_COMMON_PATH . 'views/errors/components/styles.php';

        ?>
    </head>
    <body>
        <div id="container">
            <?php

            include NAILS_COMMON_PATH . 'views/errors/components/header.php';
            echo $sMessage;
            include NAILS_COMMON_PATH . 'views/errors/components/footer.php';

            ?>
        </div>
    </body>
</html>
