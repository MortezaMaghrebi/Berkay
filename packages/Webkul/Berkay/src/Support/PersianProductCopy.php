<?php

namespace Webkul\Berkay\Support;

class PersianProductCopy
{
    /** Confirmed spelling and spacing corrections in catalog copy. */
    private const WORDS = [
        'دست دوز' => 'دست‌دوز',
        'دستدوز' => 'دست‌دوز',
        'رنگ بندی' => 'رنگ‌بندی',
        'رنگبندی' => 'رنگ‌بندی',
        'قهوه ای' => 'قهوه‌ای',
        'طرح دار' => 'طرح‌دار',
        'طرحدار' => 'طرح‌دار',
        'موم زده' => 'موم‌زده',
        'فوق العاده' => 'فوق‌العاده',
        'فوقالعاده' => 'فوق‌العاده',
        'جمع وجور' => 'جمع‌وجور',
        'جمع و جور' => 'جمع‌وجور',
        'شیک پسند' => 'شیک‌پسند',
        'درعین' => 'در عین',
        'اجرابا' => 'اجرا با',
        'انواعچرمسادهوطرحدار' => 'انواع چرم ساده و طرح‌دار',
        'بابند' => 'با بند',
        'درچت' => 'در چت',
        'وتماما' => 'و تماماً',
        'وبه' => 'و به',
        'وجذاب' => 'و جذاب',
        'وخاص' => 'و خاص',
        'دوفضای' => 'دو فضای',
        'چندفضا' => 'چند فضا',
        'رنجیری' => 'زنجیری',
        'کاملا' => 'کاملاً',
        'تماما' => 'تماماً',
        'لطفا' => 'لطفاً',
    ];

    /** Proofread Persian copy while preserving embedded markup and technical identifiers. */
    public static function edit(string $copy, bool $richText = false): string
    {
        if (! preg_match('/\p{Arabic}/u', $copy)) {
            return $copy;
        }

        $protected = <<<'REGEX'
~(<!--.*?-->|<(?:script|style|pre|code)\b[^>]*>.*?</(?:script|style|pre|code)\s*>|</?[A-Za-z](?:[^<>"']|"[^"]*"|'[^']*')*>|(?:https?://|www\.)[^\s<>]+|[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|&(?:\#\d+|\#x[\da-f]+|[a-z][a-z\d]+);)~isu
REGEX;
        $parts = preg_split($protected, $copy, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $parts[$index] = self::editText($part);
            }
        }

        $edited = implode('', $parts);

        return $richText ? $edited : trim($edited);
    }

    /** Apply only known spelling fixes and contextual spacing rules. */
    private static function editText(string $text): string
    {
        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        $text = strtr($text, ['ك' => 'ک', 'ي' => 'ی']);
        $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
        $words = implode('|', array_map(fn ($word) => preg_quote($word, '/'), array_keys(self::WORDS)));
        $text = preg_replace_callback('/(?<![\p{L}\p{M}\x{200C}])(?:'.$words.')(?![\p{L}\p{M}\x{200C}])/u', fn ($match) => self::WORDS[$match[0]], $text);

        return preg_replace(
            [
                '/(?<=\p{Arabic}) +(ها(?:ی)?)(?![\p{L}\p{M}\x{200C}])/u',
                '/(?<![\p{L}\p{M}\x{200C}])(ن?می) +(شود|شوند|باشد|باشند|کند|کنند|دهد|دهند|تواند|توانید|توانم|توانیم)(?![\p{L}\p{M}\x{200C}])/u',
                '/(?<=\p{Arabic}) +([،؛؟!:.])/u',
                '/([،؛؟])(?=\p{Arabic})(?=\p{L})/u',
                '/(?<=\p{Nd})تا(?=\s+\p{Arabic})/u',
            ],
            ["\u{200C}$1", "$1\u{200C}$2", '$1', '$1 ', ' تا'],
            $text
        );
    }
}
