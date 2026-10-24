<li class="org-branch">
    <article class="org-node-card {{ !empty($node['employees']) && count($node['employees']) ? 'org-occupied' : 'org-unfilled' }}" data-org-label="{{ strtolower($node['title'] ?? '') }}">
        <div class="org-node-heading">
            <span class="org-role-icon"><i class="bx {{ !empty($node['children']) ? 'bx-sitemap' : 'bx-user-pin' }}"></i></span>
            <span class="org-node-heading-text">
                <strong class="org-node-title">{{ $node['title'] ?: 'Untitled Designation' }}</strong>
                @if(!empty($node['title_fa']))<small class="org-node-subtitle">{{ $node['title_fa'] }}</small>@endif
            </span>
            @if(!empty($node['children']))
                <button class="org-toggle" type="button" aria-expanded="true" aria-label="Collapse {{ $node['title'] }} team" title="Collapse or expand team"><i class="bx bx-chevron-up"></i></button>
            @endif
        </div>
        <div class="org-people">
            @forelse($node['employees'] as $employee)
                <div class="org-person">
                    <span class="org-avatar">{{ mb_strtoupper(mb_substr($employee->name, 0, 1)) }}</span>
                    <span><strong>{{ $employee->name }}</strong><small><i class="bx bx-buildings"></i> {{ $employee->branch?->name ?? 'No branch' }}</small></span>
                </div>
            @empty
                <div class="org-vacant"><i class="bx bx-user-plus"></i> Vacant position</div>
            @endforelse
        </div>
    </article>
    @if(!empty($node['children']))
        <ul class="org-children">
            @foreach($node['children'] as $childNode)
                @include('dashboard._org-node', ['node' => $childNode])
            @endforeach
        </ul>
    @endif
</li>
