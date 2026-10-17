@props(['paginator'])

@php
    $isLengthAware = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    /** @var list<array{0: int|null, 1: string|null}> $pages Page number and URL pairs; a null page marks a gap. */
    $pages = [];

    if ($isLengthAware) {
        foreach (array_filter(\Illuminate\Pagination\UrlWindow::make($paginator)) as $window) {
            if ($pages !== []) {
                $pages[] = [null, null];
            }

            foreach ($window as $page => $url) {
                $pages[] = [$page, $url];
            }
        }
    }
    $pageClasses = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-sm font-medium tabular-nums transition focus-visible:outline-2 focus-visible:outline-indigo-500';
@endphp

@if ($paginator->isNotEmpty() && ($paginator->hasPages() || $isLengthAware))
    <nav {{ $attributes->class(['mt-4 flex flex-wrap items-center justify-between gap-3']) }} aria-label="Pagination">
        <p class="text-sm text-zinc-500">
            @if ($isLengthAware)
                Showing <span class="font-medium text-zinc-900 tabular-nums">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</span>
                of <span class="font-medium text-zinc-900 tabular-nums">{{ number_format($paginator->total()) }}</span>
            @else
                Page <span class="font-medium text-zinc-900 tabular-nums">{{ $paginator->currentPage() }}</span>
            @endif
        </p>

        @if ($paginator->hasPages())
            <div class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="{{ $pageClasses }} text-zinc-300" aria-disabled="true">
                        <x-super-admin.icon name="chevron-left" class="size-4" />
                        <span class="sr-only">Previous</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $pageClasses }} text-zinc-600 hover:bg-white hover:text-zinc-900 hover:shadow-xs">
                        <x-super-admin.icon name="chevron-left" class="size-4" />
                        <span class="sr-only">Previous</span>
                    </a>
                @endif

                @foreach ($pages as [$page, $url])
                    @if ($page === null)
                        <span class="{{ $pageClasses }} text-zinc-400">…</span>
                    @elseif ($page === $paginator->currentPage())
                        <span class="{{ $pageClasses }} bg-zinc-900 text-white shadow-sm" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $pageClasses }} hidden text-zinc-600 hover:bg-white hover:text-zinc-900 hover:shadow-xs sm:inline-flex">{{ $page }}</a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $pageClasses }} text-zinc-600 hover:bg-white hover:text-zinc-900 hover:shadow-xs">
                        <x-super-admin.icon name="chevron-right" class="size-4" />
                        <span class="sr-only">Next</span>
                    </a>
                @else
                    <span class="{{ $pageClasses }} text-zinc-300" aria-disabled="true">
                        <x-super-admin.icon name="chevron-right" class="size-4" />
                        <span class="sr-only">Next</span>
                    </span>
                @endif
            </div>
        @endif
    </nav>
@endif
