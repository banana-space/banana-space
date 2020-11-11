<?php

require('src/lightncandy.php');

$template = '{{> (partial_name_helper type)}}';

$data = Array(
    'type' => 'dog',
    'name' => 'Lucky',
    'age' => 5
);

function partial_name_helper ($type) {
    switch ($type[0]) {
    case 'man':
    case 'woman':
        return 'people';
    case 'dog':
    case 'cat':
        return 'animal';
    default:
        return 'default';
    }
}

$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_EXCEPTION,
    'helpers' => Array(
        'partial_name_helper'
    ),
    'partials' => Array(
        'people' => 'This is {{name}}, he is {{age}} years old.',
        'animal' => 'This is {{name}}, it is {{age}} years old.',
        'default' => 'This is {{name}}.',
    )
));

$renderer = LightnCandy::prepare($php);

echo "Data:\n";
print_r($data);

echo "\nTemplate:\n$template\n";

echo "\nCode:\n$php\n\n";

echo "\nOutput:\n";
echo $renderer($data);
echo "\n";

?>
