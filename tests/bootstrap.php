<?php declare(strict_types=1);

require dirname(__DIR__, 2) . '/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'CustomVocab', 'Annotate', '?DigitalObject', '?SpamGuard'],
    'AnnotateTest',
    __DIR__ . '/AnnotateTest'
);
