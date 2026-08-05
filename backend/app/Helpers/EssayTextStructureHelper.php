<?php

class EssayTextStructureHelper
{
    public static function decode(?string $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function buildFromPlainText(string $text, int $paragraphIndent = 28): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split("/\n\s*\n/u", $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($paragraphs) <= 1) {
            // Fallback para textos pautados sem linha em branco:
            // inicia novo parágrafo quando a linha começa com recuo (2+ espaços/tab).
            $rawLines = preg_split("/\n/u", $text);
            $rebuiltParagraphs = [];
            $currentParagraphLines = [];
            $paragraphStartByConnector = '/^(em primeiro lugar|primordialmente|ademais|nesse vi[eé]s|portanto|diante disso|consequentemente|assim|entretanto|no entanto|outrossim|por fim|em suma|em síntese)\b/iu';
            foreach ($rawLines as $idx => $rawLine) {
                $line = (string) $rawLine;
                if (trim($line) === '') {
                    if (!empty($currentParagraphLines)) {
                        $rebuiltParagraphs[] = implode("\n", $currentParagraphLines);
                        $currentParagraphLines = [];
                    }
                    continue;
                }
                $startsWithIndent = (bool) preg_match('/^[ \t]{2,}\S/u', $line);
                $startsWithConnector = (bool) preg_match($paragraphStartByConnector, ltrim($line));
                if (($startsWithIndent || $startsWithConnector) && $idx > 0 && !empty($currentParagraphLines)) {
                    $rebuiltParagraphs[] = implode("\n", $currentParagraphLines);
                    $currentParagraphLines = [];
                }
                $currentParagraphLines[] = rtrim($line);
            }
            if (!empty($currentParagraphLines)) {
                $rebuiltParagraphs[] = implode("\n", $currentParagraphLines);
            }
            if (!empty($rebuiltParagraphs)) {
                $paragraphs = $rebuiltParagraphs;
            }
        }
        $structure = [];
        $lineNumber = 1;

        foreach ($paragraphs as $paragraphIndex => $paragraphText) {
            $paragraphText = trim((string) $paragraphText);
            if ($paragraphText === '') {
                continue;
            }

            $lines = preg_split("/\n/u", $paragraphText, -1, PREG_SPLIT_NO_EMPTY);
            $structuredLines = [];

            foreach ($lines as $lineText) {
                $lineText = rtrim((string) $lineText);
                if (trim($lineText) === '') {
                    continue;
                }

                $structuredLines[] = [
                    'line_id' => 'l' . $lineNumber,
                    'text' => $lineText,
                    'line_number' => $lineNumber,
                ];
                $lineNumber++;
            }

            if (empty($structuredLines)) {
                continue;
            }

            $structure[] = [
                'paragraph_id' => 'p' . ($paragraphIndex + 1),
                'start_line' => (int) ($structuredLines[0]['line_number'] ?? $lineNumber),
                'indent' => $paragraphIndent,
                'lines' => $structuredLines,
            ];
        }

        return $structure;
    }

    public static function toPlainText(array $textStructure): string
    {
        $paragraphs = [];

        foreach ($textStructure as $paragraph) {
            $lines = [];
            foreach ((array) ($paragraph['lines'] ?? []) as $line) {
                $text = trim((string) ($line['text'] ?? ''));
                if ($text !== '') {
                    $lines[] = $text;
                }
            }

            if (!empty($lines)) {
                $paragraphs[] = implode("\n", $lines);
            }
        }

        return implode("\n\n", $paragraphs);
    }

    public static function flatten(array $textStructure): array
    {
        $flatLines = [];
        $fallbackLineNumber = 1;

        foreach ($textStructure as $paragraph) {
            $paragraphId = (string) ($paragraph['paragraph_id'] ?? '');
            $paragraphLines = (array) ($paragraph['lines'] ?? []);
            $isFirstNonEmptyLine = true;
            foreach ($paragraphLines as $line) {
                $text = (string) ($line['text'] ?? '');

                $lineNumber = (int) ($line['line_number'] ?? 0);
                if ($lineNumber <= 0) {
                    $lineNumber = $fallbackLineNumber;
                }

                $isVisibleLine = trim($text) !== '';

                $flatLines[] = [
                    'paragraph_id' => $paragraphId,
                    'line_id' => (string) ($line['line_id'] ?? ('l' . $lineNumber)),
                    'line_number' => $lineNumber,
                    'text' => $text,
                    'is_paragraph_start' => $isFirstNonEmptyLine && $isVisibleLine,
                ];
                if ($isVisibleLine) {
                    $isFirstNonEmptyLine = false;
                }
                $fallbackLineNumber = $lineNumber + 1;
            }
        }

        return $flatLines;
    }

    public static function countLines(array $textStructure): int
    {
        return count(self::flatten($textStructure));
    }

    public static function normalizeStructure(array $payload): array
    {
        $lineNumber = 1;
        $structure = [];

        foreach ((array) ($payload['text_structure'] ?? []) as $paragraphIndex => $paragraph) {
            $structuredLines = [];
            foreach ((array) ($paragraph['lines'] ?? []) as $line) {
                $text = rtrim((string) ($line['text'] ?? ''));
                if (trim($text) === '') {
                    continue;
                }

                $structuredLines[] = [
                    'line_id' => (string) ($line['line_id'] ?? ('l' . $lineNumber)),
                    'text' => $text,
                    'line_number' => (int) ($line['line_number'] ?? $lineNumber),
                ];
                $lineNumber++;
            }

            if (empty($structuredLines)) {
                continue;
            }

            $structure[] = [
                'paragraph_id' => (string) ($paragraph['paragraph_id'] ?? ('p' . ($paragraphIndex + 1))),
                'start_line' => (int) ($paragraph['start_line'] ?? ($structuredLines[0]['line_number'] ?? 0)),
                'indent' => (int) ($paragraph['indent'] ?? 28),
                'lines' => $structuredLines,
            ];
        }

        return $structure;
    }
}
