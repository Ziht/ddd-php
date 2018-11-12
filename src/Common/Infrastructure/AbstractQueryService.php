<?php

namespace AlephTools\DDD\Common\Infrastructure;

use AlephTools\DDD\Common\Infrastructure\SqlBuilder\Query;
use AlephTools\DDD\Common\Model\Exceptions\InvalidArgumentException;

abstract class AbstractQueryService
{
    protected function applySelection(Query $query, ?array $fields, ?array $selectFields): Query
    {
        $selectFields = $selectFields ?: [];

        if ($fields) {
            foreach ($fields as $field) {
                if (isset($selectFields[$field])) {
                    $query->select($selectFields[$field], $field);
                } else {
                    throw new InvalidArgumentException("Incorrect field: $field.");
                }
            }
        } else {
            foreach ($selectFields as $alias => $column) {
                $query->select($column, $alias);
            }
        }

        return $query;
    }

    protected function applySorting(Query $query, ?array $sort, ?array $sortFields): Query
    {
        if ($sort) {
            $sortFields = $sortFields ?: [];

            foreach ($sort as $column => $order) {
                if (isset($sortFields[$column])) {
                    $query->orderBy($sortFields[$column], $order);
                } else {
                    throw new InvalidArgumentException("Incorrect sort field: $column.");
                }
            }
        }

        return $query;
    }

    protected function applyPagination(Query $query, AbstractQuery $request): Query
    {
        if ($request->limit !== null) {
            $query->limit($request->limit);
        }
        if ($request->offset !== null) {
            $query->offset($request->offset);
        } else if ($request->page !== null) {
            $query->paginate($request->page, $request->limit ?: AbstractQuery::DEFAULT_PAGE_SIZE);
        }
        return $query;
    }

    protected function applyDateRangeFiltering(Query $query, string $column, AbstractQuery $request)
    {
        if ($request->from && $request->to) {
            $query->where($column, 'BETWEEN', [$request->from, $request->to]);
        } else if ($request->from) {
            $query->where($column, '>=', $request->from);
        } else if ($request->to) {
            $query->where($column, '<=', $request->to);
        }
    }
}