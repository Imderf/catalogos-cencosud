<?php

namespace App\Domain\Projects;

/** Mentions are written as @[Nombre](id) inside comment bodies. */
final class Mentions
{
    /** @return list<int> */
    public static function ids(string $body): array
    {
        preg_match_all('/@\[[^\]]+\]\((\d+)\)/', $body, $m);

        return array_values(array_unique(array_map('intval', $m[1])));
    }

    /** Plain text for emails and previews: "@Nombre". */
    public static function plain(string $body): string
    {
        return preg_replace('/@\[([^\]]+)\]\(\d+\)/', '@$1', $body) ?? $body;
    }
}
