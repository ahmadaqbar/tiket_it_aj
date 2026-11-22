<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;

/**
 * Baris spreadsheet yang aman dari formula injection.
 * Cell::fromValue() openspout mengubah string berawalan "=" menjadi FormulaCell; data dari user (judul tiket, nama, dll.)
 * karena itu SELALU ditulis sebagai StringCell (teks), sehingga Excel tidak pernah mengeksekusinya sebagai formula.
 */
final class SafeRow
{
    /** @param array<int, mixed> $values */
    public static function make(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(
            fn ($v) => is_string($v) && $v !== '' ? new StringCell($v, $style) : Cell::fromValue($v, $style),
            array_values($values),
        ), $style);
    }
}
