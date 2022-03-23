<?php

function tablevel(int $level): string
{
    $output = "";
    while ($level > 0) {
        $output .= "    ";
        $level--;
    }
    return $output;
}
