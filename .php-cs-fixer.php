<?php

return My\PhpCsFixerConfig::create()
    ->setFinder(
        PhpCsFixer\Finder::create()
            // NoUnusedImportsFixer
            ->files()
            ->name('*.php')
            ->in(__DIR__.'/src')
            ->in(__DIR__.'/tests')
    );
