<?php

function processDelimiters($sql)
{
    $sql = preg_replace('/DELIMITER\s*(\S+)/', '', $sql);
    
    $sql = str_replace('$$', ';', $sql);
    
    return $sql;
}