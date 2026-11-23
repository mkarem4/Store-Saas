<?php

namespace App\Enums;

/** Built-in modules only; BusinessType.key stays a string to support custom types. */
enum BusinessTypeKey: string
{
    case CarShowroom = 'car_showroom';
    case Electronics = 'electronics';
    case CarRental = 'car_rental';
    case Jewelry = 'jewelry';
    case Perfume = 'perfume';
    case Clothing = 'clothing';
    case Grocery = 'grocery';
    case Restaurant = 'restaurant';
}
