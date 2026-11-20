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
        'qty_usage_display',
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

    public static function formatDisplayInput($raw, float $numericVal): string
    {
        if ($numericVal <= 0) {
            return '0';
        }

        $cleanDec = rtrim(rtrim(number_format($numericVal, 8, '.', ''), '0'), '.');
        if ($cleanDec === '' || $cleanDec === '-0') {
            $cleanDec = '0';
        }

        $expr = str_replace([',', ':'], ['.', '/'], trim((string) $raw));
        $expr = preg_replace('/\s+/', '', $expr);

        if (preg_match('/^(\d+)\/(\d+)$/', $expr, $m)) {
            $num = (int) $m[1];
            $den = (int) $m[2];
            if ($num > 0 && $den > 0) {
                if ($num % $den === 0) {
                    return (string) intdiv($num, $den);
                }
                return "{$num}/{$den}";
            }
        }

        return $cleanDec;
    }

    public function getFormattedQtyUsageAttribute(): string
    {
        if (!empty($this->qty_usage_display)) {
            return (string) $this->qty_usage_display;
        }

        $val = (float) $this->qty_usage;
        if ($val <= 0) {
            return '0';
        }

        $cleanDec = rtrim(rtrim(number_format($val, 8, '.', ''), '0'), '.');
        return ($cleanDec === '' || $cleanDec === '-0') ? '0' : $cleanDec;
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