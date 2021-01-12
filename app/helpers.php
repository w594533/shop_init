<?php
function route_class()
{
    return str_replace('.', '-', Route::currentRouteName());
}

// 默认的精度为小数点后两位
function big_number($number, $scale = 2)
{
    // return new \Moontoast\Math\BigNumber($number, $scale);
}