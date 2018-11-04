<?php namespace Tekton\Components;

interface ComponentInterface
{
    function setId(string $id);

    function getId();

    function setIndex(int $index);

    function getIndex();

    function setName(string $name);

    function getName();

    function render($data = []);

    function processData($data);

    function set($key, $value);

    function get($key, $default);

    function has($key);
}
