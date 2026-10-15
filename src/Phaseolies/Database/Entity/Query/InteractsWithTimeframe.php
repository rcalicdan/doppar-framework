<?php

namespace Phaseolies\Database\Entity\Query;

trait InteractsWithTimeframe
{
    /**
     * Add a where date clause to the query.
     */
    public function whereDate(string $column, mixed $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        $this->assertValidIdentifier($column);

        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            "DATE($column)",
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where date clause to the query.
     */
    public function orWhereDate(string $column, mixed $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        return $this->whereDate($column, $operator, $value, 'OR');
    }

    /**
     * Add a where month clause to the query.
     */
    public function whereMonth(string $column, mixed $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        $this->assertValidIdentifier($column);

        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->month($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where month clause to the query.
     */
    public function orWhereMonth(string $column, mixed $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        return $this->whereMonth($column, $operator, $value, 'OR');
    }

    /**
     * Add a where year clause to the query.
     */
    public function whereYear(string $column, mixed $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        $this->assertValidIdentifier($column);

        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->year($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where year clause to the query.
     */
    public function orWhereYear(string $column, mixed $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        return $this->whereYear($column, $operator, $value, 'OR');
    }

    /**
     * Add a where day clause to the query.
     */
    public function whereDay(string $column, mixed $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        $this->assertValidIdentifier($column);

        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->day($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where day clause to the query.
     */
    public function orWhereDay(string $column, mixed $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        return $this->whereDay($column, $operator, $value, 'OR');
    }

    /**
     * Add a where time clause to the query.
     */
    public function whereTime(string $column, mixed $operator, ?string $value = null, string $boolean = 'AND'): self
    {
        $this->assertValidIdentifier($column);

        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        $this->conditions[] = [
            $boolean,
            $this->time($column),
            $operator,
            $value
        ];

        return $this;
    }

    /**
     * Add an OR where time clause to the query.
     */
    public function orWhereTime(string $column, mixed $operator, ?string $value = null): self
    {
        if (func_num_args() === 2) {
            $value = (string) $operator;
            $operator = '=';
        }

        return $this->whereTime($column, $operator, $value, 'OR');
    }

    /**
     * Filter records for today's date.
     */
    public function whereToday(string $column): self
    {
        return $this->whereDate($column, now()->toDateString());
    }

    /**
     * Filter records for yesterday's date.
     */
    public function whereYesterday(string $column): self
    {
        return $this->whereDate($column, now()->subDay()->toDateString());
    }

    /**
     * Filter records for yesterday's date with OR condition.
     */
    public function orWhereYesterday(string $column): self
    {
        return $this->orWhereDate($column, now()->subDay()->toDateString());
    }

    /**
     * Filter records for this month.
     */
    public function whereThisMonth(string $column): self
    {
        return $this->whereMonth($column, (string) now()->month);
    }

    /**
     * Filter records for last month.
     */
    public function whereLastMonth(string $column): self
    {
        return $this->whereMonth($column, (string) now()->subMonth()->month);
    }

    /**
     * Filter records for this year.
     */
    public function whereThisYear(string $column): self
    {
        return $this->whereYear($column, (string) now()->year);
    }

    /**
     * Filter records for last year.
     */
    public function whereLastYear(string $column): self
    {
        return $this->whereYear($column, (string) now()->subYear()->year);
    }

    /**
     * Add a where date between condition (date only, excludes time).
     */
    public function whereDateBetween(string $column, mixed $start, mixed $end): self
    {
        $this->assertValidIdentifier($column);

        $start = $this->formatDate($start);
        $end = $this->formatDate($end);

        $this->conditions[] = [
            'AND',
            $this->date($column),
            'BETWEEN',
            $start,
            $end,
            'AND'
        ];

        return $this;
    }

    /**
     * Add a where datetime between condition (includes time).
     */
    public function whereDateTimeBetween(string $column, mixed $start, mixed $end): self
    {
        $start = $this->formatDateTime($start, 'start');
        $end = $this->formatDateTime($end, 'end');

        return $this->whereBetween($column, [$start, $end]);
    }

    /**
     * Format date for database comparison.
     */
    public function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date)) {
            return substr($date, 0, 10);
        }

        throw new \InvalidArgumentException('Invalid date provided');
    }

    /**
     * Format datetime for database comparison with proper time handling.
     *
     * @param string $type 'start' or 'end' to indicate how to handle missing time
     */
    public function formatDateTime(mixed $date, string $type = 'start'): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        if (is_string($date)) {
            if (strlen($date) > 10 && str_contains($date, ' ')) {
                return $date;
            }

            if (strlen($date) === 10) {
                return $type === 'start' 
                    ? $date . ' 00:00:00'
                    : $date . ' 23:59:59';
            }

            return $date;
        }

        throw new \InvalidArgumentException('Invalid date provided');
    }
}