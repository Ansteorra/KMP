<?php
declare(strict_types=1);

namespace App\KMP;

use DateTimeInterface;

/** Society dates use the event-local calendar date, with A.S. I beginning May 1, 1966. */
class AnnoSocietatis
{
    /**
     * @param \DateTimeInterface $date Already converted to the display timezone
     * @return int|null Null before the Society epoch
     */
    public static function year(DateTimeInterface $date): ?int
    {
        $year = (int)$date->format('Y') - ((int)$date->format('n') < 5 ? 1966 : 1965);

        return $year > 0 ? $year : null;
    }

    /**
     * @param \DateTimeInterface $date Already converted to the display timezone
     * @return string Empty before the Society epoch
     */
    public static function format(DateTimeInterface $date): string
    {
        $year = self::year($date);
        if ($year === null) {
            return '';
        }
        $roman = '';
        $numerals = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC',
            50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];
        foreach ($numerals as $value => $numeral) {
            while ($year >= $value) {
                $roman .= $numeral;
                $year -= $value;
            }
        }

        return $date->format('j F') . ' A.S. ' . $roman;
    }

    /**
     * @param \DateTimeInterface $start Event-local start
     * @param \DateTimeInterface $end Event-local end
     * @return string Both dates, including their own Society years at the May boundary
     */
    public static function range(DateTimeInterface $start, DateTimeInterface $end): string
    {
        $first = self::format($start);
        $last = self::format($end);

        return $first === $last ? $first : implode(' → ', array_filter([$first, $last]));
    }
}
