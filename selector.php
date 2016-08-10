<?php
/**
 * Created by PhpStorm.
 * User: BPratt
 * Date: 25/05/2015
 * Time: 11:42 AM
 */

require_once("vendor/autoload.php");

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \app\SelectMovies());
$application->run();