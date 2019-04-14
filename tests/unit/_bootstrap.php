<?php

$basePath = dirname(dirname(__DIR__));
$vandorPath = $basePath."/vendor";
require("$vandorPath/autoload.php");


spl_autoload_register(function ($class) {
    $basePath = dirname(dirname(__DIR__));
    if ($class == 'serj\sortable\Sortable') {
        include "$basePath/src/Sortable.php";
    }
    if ($class == 'SortableBase') {
        include "$basePath/tests/unit/SortableBase.php";
    }
    else if ($class == 'Yii') {
        include "$basePath/vendor/yiisoft/yii2/Yii.php";
    }
});