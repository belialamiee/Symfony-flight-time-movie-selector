<?php
/**
 * Created by PhpStorm.
 * User: BPratt
 * Date: 11/08/2016
 * Time: 9:42 PM
 */

require_once("vendor/autoload.php");

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \app\SelectMovies());
$application->run();
