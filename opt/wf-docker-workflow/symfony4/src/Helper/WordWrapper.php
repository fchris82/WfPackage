<?php declare(strict_types=1);
/**
 * Created by IntelliJ IDEA.
 * User: chris
 * Date: 2018.11.23.
 * Time: 15:46
 */

namespace App\Helper;

class WordWrapper
{
    protected $width;
    protected $break;

    protected $newLines;
    protected $newLineTokens;
    protected $currentLength;

    public function __construct($width, $break)
    {
        $this->width = $width;
        $this->break = $break;
    }

    protected function closeLine(): void
    {
        if (\count($this->newLineTokens)) {
            $this->newLines[] = implode(' ', $this->newLineTokens);
            $this->newLineTokens = [];
            $this->currentLength = 0;
        }
    }

    protected function addTokenToLine($token, $virtualTokenLength): void
    {
        if ($token) {
            $this->newLineTokens[] = $token;
            $this->currentLength += $virtualTokenLength;
        }
    }

    protected function finish(): string
    {
        $this->closeLine();

        return implode($this->break, $this->newLines);
    }

    protected function reset(): void
    {
        $this->newLineTokens = [];
        $this->newLines = [];
    }

    protected function getCurrentLineLength(): int
    {
        return $this->currentLength + \count($this->newLineTokens) - 1;
    }

    protected function getVirtualTokenLength(string $token): int
    {
        $virtualTokenLength = mb_strlen($token);
        if (false !== strpos($token, '<')) {
            $untaggedToken = preg_replace('/<[^>]+>/', '', $token);
            $virtualTokenLength = mb_strlen($untaggedToken);
        }

        return $virtualTokenLength;
    }

    public function formattedStringWordwrap(string $string, $cut = true): string
    {
        $this->reset();
        $lines = explode($this->break, $string);
        foreach ($lines as $n => $line) {
            foreach (explode(' ', $line) as $token) {
                $virtualTokenLength = $this->getVirtualTokenLength($token);
                $lineLength = $this->getCurrentLineLength();
                // If width would be greater with the new token/word. The count()-1 is the number of spaces!
                if ($lineLength + $virtualTokenLength < $this->width) {
                    $this->addTokenToLine($token, $virtualTokenLength);
                } else {
                    if ($virtualTokenLength < $this->width) {
                        $this->closeLine();
                        $this->addTokenToLine($token, $virtualTokenLength);
                    } elseif (!$cut || 'http' == mb_substr($token, 0, 4)) {
                        $this->closeLine();
                        $this->addTokenToLine($token, $virtualTokenLength);
                        $this->closeLine();
                    } else {
                        $this->handleLongToken($token);
                    }
                }
            }
            $this->closeLine();
        }

        return $this->finish();
    }

    protected function handleLongToken($token): void
    {
        $freeChars = $this->width - ($this->getCurrentLineLength() + 1);
        if ($freeChars < 5) {
            $this->closeLine();
            $freeChars = $this->width;
        }
        $tokenBlocks = explode(' ', preg_replace('/<[^>]+>/', ' \\0 ', $token));
        $slicedToken = '';
        $slicedTokenVirtualLength = 0;
        foreach ($tokenBlocks as $block) {
            do {
                list($token, $block, $blockLength) = $this->sliceToken($block, $freeChars);
                $freeChars -= $blockLength;
                $slicedTokenVirtualLength += $blockLength;
                $slicedToken .= $token;
                if (!$freeChars) {
                    $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
                    $this->closeLine();
                    $slicedToken = '';
                    $slicedTokenVirtualLength = 0;
                    $freeChars = $this->width;
                }
            } while ($block);
        }
        $this->addTokenToLine($slicedToken, $slicedTokenVirtualLength);
    }

    protected function sliceToken($token, $freeChars): array
    {
        if ('<' == $token[0] && '>' == mb_substr($token, -1)) {
            return [$token, '', 0];
        }
        $blockLength = mb_strlen($token);
        if ($blockLength <= $freeChars) {
            return [$token, '', $blockLength];
        }

        return [
            mb_substr($token, 0, $freeChars),
            mb_substr($token, $freeChars),
            $freeChars,
        ];
    }
}
