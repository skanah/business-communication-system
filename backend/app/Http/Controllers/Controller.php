<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Return a paginated API response with consistent `links` and `meta` keys.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     * @param  array  $extra
     * @return array
     */
    protected function respondWithPagination($paginator, array $extra = [])
    {
        $paginator->setPageName('page');

        $linkItems = [];
        for ($i = 1; $i <= $paginator->lastPage(); $i++) {
            $linkItems[] = [
                'url' => $paginator->url($i),
                'label' => (string) $i,
                'active' => $i === $paginator->currentPage(),
            ];
        }

        $links = [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl() ? $paginator->previousPageUrl() : null,
            'next' => $paginator->nextPageUrl() ? $paginator->nextPageUrl() : null,
        ];

        $meta = [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'links' => $linkItems,
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];

        return [
            'success' => true,
            'data' => array_merge([
                'data' => $paginator->items(),
                'links' => $links,
                'meta' => $meta,
            ], $extra),
        ];
    }
}
