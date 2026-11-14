<?php

namespace App\Exceptions;

use RuntimeException;

final class UnresolvedCutoffTieException extends RuntimeException
{
    /**
     * @param  array<int, int>  $tiedContestantIds
     */
    public function __construct(
        public readonly int $roundId,
        public readonly int $advancementCount,
        public readonly array $tiedContestantIds,
    ) {
        parent::__construct(
            "An unbroken tie straddles the Top {$advancementCount} advancement cutoff for Round #{$roundId}. Explicit qualifier selection or committee override reason is required."
        );
    }
}
