<?php declare(strict_types=1);

$genericDir = file_exists(dirname(__DIR__, 2) . '/Generic/ModuleTester.php')
    ? dirname(__DIR__, 2)
    : __DIR__;
require_once $genericDir . '/Generic/TesterTrait.php';
require_once $genericDir . '/Generic/ModuleTester.php';

$moduleName = basename(dirname(__DIR__));
$tester = new \Generic\ModuleTester($moduleName);
$tester->initModule();

file_put_contents('php://stdout', sprintf("%s: Running tests…\n\n", $moduleName));
