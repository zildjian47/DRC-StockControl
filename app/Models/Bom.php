<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    use HasFactory;

    protected $fillable = [
        'bom_group',
        'process_id',
        'output_item_id',
        'input_item_id',
        'qty_usage',
        'is_trigger',
    ];

    protected $casts = [
        'qty_usage'  => 'decimal:8',
        'is_trigger' => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function outputItem()
    {
        return $this->belongsTo(Item::class, 'output_item_id')->withTrashed();
    }

    public function inputItem()
    {
        return $this->belongsTo(Item::class, 'input_item_id')->withTrashed();
    }

    public static function parseCalculatorValue($raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $expr = trim(str_replace([',', 'x', 'X', ':'], ['.', '*', '*', '/'], (string) $raw));
        $expr = preg_replace('/\s+/', '', $expr);

        if ($expr === '' || !preg_match('/^[0-9.+\-*\/()]+$/', $expr)) {
            return null;
        }

        preg_match_all('/\d+(?:\.\d+)?|[+\-*\/()]/', $expr, $matches);
        $tokens = $matches[0] ?? [];
        if (empty($tokens) || implode('', $tokens) !== $expr) {
            return null;
        }

        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $output = [];
        $ops = [];

        foreach ($tokens as $idx => $t) {
            if (is_numeric($t)) {
                $output[] = (float) $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while (!empty($ops) && end($ops) !== '(') {
                    $output[] = array_pop($ops);
                }
                if (empty($ops)) {
                    return null;
                }
                array_pop($ops);
            } elseif (isset($prec[$t])) {
                if ($t === '-' && ($idx === 0 || $tokens[$idx - 1] === '(')) {
                    $output[] = 0.0;
                }
                while (!empty($ops) && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                    $output[] = array_pop($ops);
                }
                $ops[] = $t;
            }
        }

        while (!empty($ops)) {
            $op = array_pop($ops);
            if ($op === '(' || $op === ')') {
                return null;
            }
            $output[] = $op;
        }

        $stack = [];
        foreach ($output as $token) {
            if (is_float($token) || is_int($token)) {
                $stack[] = (float) $token;
            } else {
                if (count($stack) < 2) {
                    return null;
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                if ($token === '+') $stack[] = $a + $b;
                elseif ($token === '-') $stack[] = $a - $b;
                elseif ($token === '*') $stack[] = $a * $b;
                elseif ($token === '/') {
                    if (abs($b) < 1e-12) {
                        return null;
                    }
                    $stack[] = $a / $b;
                }
            }
        }

        if (count($stack) !== 1) {
            return null;
        }

        $res = (float) $stack[0];
        return $res > 0 ? round($res, 8) : null;
    }

    public function getFormattedQtyUsageAttribute(): string
    {
        $val = (float) $this->qty_usage;
        if ($val <= 0) {
            return '0';
        }

        $cleanDec = rtrim(rtrim(number_format($val, 8, '.', ''), '0'), '.');

        for ($den = 2; $den <= 24; $den++) {
            $num = round($val * $den);
            if ($num > 0 && abs(($num / $den) - $val) < 0.000001) {
                if ($num % $den === 0) {
                    return (string) ($num / $den);
                }
                if (in_array($den, [3, 6, 7, 9, 11, 12, 13, 14, 15, 17, 18, 19, 21, 22, 23, 24], true)) {
                    return "{$num}/{$den} ({$cleanDec})";
                }
            }
        }

        return $cleanDec;
    }

    public function getGroupTypeAttribute(): string
    {
        $grp = (string) $this->bom_group;
        if (str_starts_with($grp, 'CB-')) {
            return 'KOMBINASI';
        }
        if (str_starts_with($grp, 'MO-')) {
            return 'MULTI-OUTPUT';
        }
        if (str_starts_with($grp, 'MI-')) {
            return 'MULTI-INPUT';
        }
        return 'SINGLE';
    }
}