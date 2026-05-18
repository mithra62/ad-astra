@if ($paginator->hasPages())
    <div class="app-pagination-link float-right">
        <ul class="pagination app-pagination float-right">
            {{-- Previous Page Link --}}
            @if (!$paginator->onFirstPage())
                <li class="page-item"><a class="page-link b-r-left"
                                         href="{{ $paginator->previousPageUrl() }}">Previous</a></li>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}">Next</a></li>
            @endif
        </ul>
    </div>
@endif
