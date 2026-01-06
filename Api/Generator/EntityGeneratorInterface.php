<?php

namespace Ascorak\Faker\Api\Generator;

interface EntityGeneratorInterface
{
    public const string GENERATOR_CODE = '';

    public function generate();
}