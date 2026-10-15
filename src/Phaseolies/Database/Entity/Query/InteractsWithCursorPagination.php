<?php

namespace Phaseolies\Database\Entity\Query;

trait InteractsWithCursorPagination
{
    /**
     * Cursor pagination with forward only support.
     *
     * @return array{data: array<int, mixed>, per_page: int, next_cursor: string|null, next_page_url: string|null, has_more: bool, direction: string}
     */
    public function cursorPaginate(int $perPage = 15, string $cursorColumn = 'id', string $direction = 'asc', ?string $cursor = null): array
    {
        $direction   = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $operator    = $direction === 'asc' ? '>' : '<';
        $cursorValue = $cursor ? $this->decodeCursor($cursor) : null;

        $query = clone $this;

        if ($cursorValue !== null) {
            $query->where($cursorColumn, $operator, $cursorValue);
        }

        $query->orderBy($cursorColumn, strtoupper($direction));
        $query->limit($perPage + 1);

        $results = $query->get();
        $items   = $results->all();

        $hasMore = count($items) > $perPage;

        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $last       = end($items);
            $nextCursor = $this->encodeCursor($this->getCursorValue($last, $cursorColumn));
        }

        $path    = $this->getCurrentPath();
        $nextUrl = $nextCursor
            ? "{$path}?cursor={$nextCursor}&per_page={$perPage}&direction={$direction}"
            : null;

        return [
            'data'          => $items,
            'per_page'      => $perPage,
            'next_cursor'   => $nextCursor,
            'next_page_url' => $nextUrl,
            'has_more'      => $hasMore,
            'direction'     => $direction,
        ];
    }

    /**
     * Encode a cursor value to a URL-safe base64 string.
     */
    protected function encodeCursor(mixed $value): string
    {
        return base64_encode((string) json_encode(['v' => $value]));
    }

    /**
     * Decode a cursor string back to its original value.
     */
    protected function decodeCursor(string $cursor): mixed
    {
        try {
            $decodedStr = base64_decode($cursor, true);
            if ($decodedStr === false) {
                return null;
            }
            
            $decoded = json_decode($decodedStr, true);
            return is_array($decoded) ? ($decoded['v'] ?? null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract the cursor column value from a model or array item.
     */
    protected function getCursorValue(mixed $item, string $column): mixed
    {
        if (is_array($item)) {
            return $item[$column] ?? null;
        }

        if (is_object($item)) {
            return $item->{$column} ?? null;
        }

        return null;
    }

    /**
     * Get current request path.
     */
    protected function getCurrentPath(): string
    {
        return \Phaseolies\Support\Facades\URL::current();
    }
}