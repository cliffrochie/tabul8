<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class EventCollection extends ApiCollection
{
    public $collects = EventResource::class;

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'message' => 'Events loaded.',
            'errors' => null,
        ];
    }
}
