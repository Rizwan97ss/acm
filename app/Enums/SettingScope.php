<?php

namespace App\Enums;

enum SettingScope: string
{
    case Global = 'global';
    case School = 'school';
    case User = 'user';
}
