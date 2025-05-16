<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('Tests/_fixtures')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PER-CS2.0' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_align' => true,
        'phpdoc_order' => true,
        'no_extra_blank_lines' => true,
        'single_quote' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
    ])
    ->setFinder($finder)
;