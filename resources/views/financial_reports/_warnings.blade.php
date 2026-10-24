@if(!empty($report['warnings']))
    <div class="alert alert-warning border-0 shadow-sm mt-3 mb-3 no-print">
        <div class="d-flex gap-2">
            <i class="bx bx-error-circle fs-4"></i>
            <div>
                <strong>Report checks</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($report['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
