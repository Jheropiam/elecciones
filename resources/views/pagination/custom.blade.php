@if ($paginator->hasPages())
<div class="custom-pagination">
    <div class="pagination-left">
        <div class="pagination-summary">
            Mostrando <strong>{{ $paginator->firstItem() ?? 0 }}</strong> a <strong>{{ $paginator->lastItem() ?? 0 }}</strong> de <strong>{{ $paginator->total() }}</strong> registros
        </div>
        <form method="GET" action="{{ request()->url() }}" class="pagination-per-page-form">
            @foreach(request()->query() as $key => $value)
                @if ($key !== 'per_page' && !preg_match('/^page(?:_|$)/', (string) $key) && is_scalar($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label class="pagination-per-page-label" for="perPageSelect-{{ $paginator->getPageName() }}">Ver</label>
            <select
                id="perPageSelect-{{ $paginator->getPageName() }}"
                name="per_page"
                class="pagination-per-page-select"
                onchange="this.form.submit()"
                aria-label="Cantidad de registros por página"
            >
                @foreach([10, 20, 50, 100] as $option)
                    <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <nav class="pagination-links" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="pagination-btn disabled">‹ Anterior</span>
        @else
            <a class="pagination-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Anterior</a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pagination-ellipsis">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pagination-number active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pagination-number" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a class="pagination-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente ›</a>
        @else
            <span class="pagination-btn disabled">Siguiente ›</span>
        @endif
    </nav>
</div>
@endif
