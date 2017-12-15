<!DOCTYPE html>
<!--

    ERROR:   401
    MESSAGE: <?=strip_tags($sMessage)?>

-->
<!--[if lt IE 7 ]><html class="ie ie6" lang="en"> <![endif]-->
<!--[if IE 7 ]><html class="ie ie7" lang="en"> <![endif]-->
<!--[if IE 8 ]><html class="ie ie8" lang="en"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="en"> <!--<![endif]-->
<head>
    <title><?='Unauthorised - ' . APP_NAME?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php

    include NAILS_COMMON_PATH . 'errors/components/styles.php';

    ?>
</head>
<body>
    <div id="container">
        <?php

        if (defined('FCPATH') && defined('BASE_URL')) {

            $paths   = array();
            $paths[] = array(
                FCPATH . 'assets/img/errors/401.png',
                BASE_URL . 'assets/img/errors/401.png'
            );
        }

        include NAILS_COMMON_PATH . 'errors/components/header.php';

        echo $sMessage;

        include NAILS_COMMON_PATH . 'errors/components/footer.php';

        ?>
    </div>
</body>
