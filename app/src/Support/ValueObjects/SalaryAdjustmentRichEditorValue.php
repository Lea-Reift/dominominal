<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class SalaryAdjustmentRichEditorValue
{
    public const string TEXT_NODE_TYPE = 'text';
    public const string MERGE_TAG_NODE_TYPE = 'mergeTag';

    protected string $value;
    protected Collection $content;

    public function __construct(protected string $rawFormula)
    {
        $content = json_decode($rawFormula, true);

        if (!isset($content['content'])) {
            throw new InvalidArgumentException('Formula no tiene el formato del RichEditor');
        }

        $this->content = collect($content['content']);
    }

    public function value(): string
    {
        if (!isset($this->value)) {
            $this->value = $this->parseValue();
        }

        return $this->value;
    }

    protected function parseValue(): string
    {
        $lines = collect();
        collect($this->content)
            ->pluck('content')
            ->each(function (array $rawLine) use ($lines) {
                $line = '';
                foreach ($rawLine as $extract) {
                    $line .= match ($extract['type']) {
                        self::MERGE_TAG_NODE_TYPE => $extract['attrs']['id'],
                        default => $extract['text'],
                    };
                }

                $lines->push($line);
            });

        return $lines->join(';');
    }
}
