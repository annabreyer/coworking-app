<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony'               => true,
        '@Symfony:risky'         => true,
        '@PSR12'                 => true,
        '@DoctrineAnnotation'    => true,
        '@PhpCsFixer:risky'      => true,
        'declare_strict_types'   => true,
        'concat_space'           => ['spacing' => 'one'],
        'binary_operator_spaces' => [
            'default'   => 'align_single_space_minimal',
            'operators' => [
                '|' => 'no_space',
            ],
        ],
        'yoda_style'             => true,
    ])
    ->setFinder($finder)
;
