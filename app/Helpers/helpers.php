<?php


function xcashRedirect($url)
{
    wp_redirect($url);
    exit;
}

if (!function_exists('xcash_title_to_key')) {
    function xcash_title_to_key($text)
    {
        return strtolower(str_replace(' ', '_', $text));
    }
}