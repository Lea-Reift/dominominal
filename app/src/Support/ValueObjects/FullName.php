<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

readonly class FullName implements Arrayable
{
    protected const SPACE_CHARACTER = ' ';

    public function __construct(
        public string $name,
        public string $surname,
    ) {
    }

    public function toArray(): array
    {
        return (array) $this;
    }

    public static function make(
        string $name,
        string $surname,
    ): self {
        return new self(
            name: $name,
            surname: $surname
        );
    }

    public static function fromFullNameString(string $fullName): self
    {
        $words = str($fullName)
            ->deduplicate(static::SPACE_CHARACTER)
            ->trim()
            ->convertCase(MB_CASE_TITLE_SIMPLE)
            ->explode(static::SPACE_CHARACTER);


        if ($words->count() === 2) {
            return self::make(
                name: $words[0],
                surname: $words[1],
            );
        }

        if ($words->count() === 1) {
            return self::make(
                name: $words[0],
                surname: '',
            );
        }

        if ($words->count() === 3) {
            $surname = $words->pop();
            return self::make(
                name: $words->join(static::SPACE_CHARACTER),
                surname: $surname,
            );
        }

        $words = str($words->join(static::SPACE_CHARACTER))->convertCase(MB_CASE_UPPER)->explode(static::SPACE_CHARACTER);

        $surname = collect();

        $compositeTokens = collect(['DE', 'DEL', 'LA', 'LOS', 'LAS']);

        $composites = 0;

        while ($words->count() > 0) {
            $word = $words->pop();
            $surname->unshift($word);

            $nextWord = $words->last();

            if ($compositeTokens->contains($word)) {
                $composites++;
            }

            if ($compositeTokens->doesntContain($nextWord) && ($surname->count() - $composites) >= 2) {
                break;
            }
        }

        $name = $words->join(static::SPACE_CHARACTER);
        $surname = $surname->join(static::SPACE_CHARACTER);

        return self::make(
            name: Str::convertCase($name, MB_CASE_TITLE_SIMPLE),
            surname: Str::convertCase($surname, MB_CASE_TITLE_SIMPLE),
        );
    }
}
