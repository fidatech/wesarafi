<?php

namespace App\Support;

use Carbon\CarbonInterface;

class JalaliDate
{
    public static function fromGregorian(string|CarbonInterface|null $date): string
    {
        if ($date instanceof CarbonInterface) {
            $gy = (int) $date->format('Y');
            $gm = (int) $date->format('m');
            $gd = (int) $date->format('d');
        } elseif (is_string($date) && $date !== '') {
            [$gy, $gm, $gd] = array_map('intval', explode('-', substr($date, 0, 10)));
        } else {
            return '';
        }

        $gDayMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = (365 * $gy)
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $gDayMonth[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }
}
