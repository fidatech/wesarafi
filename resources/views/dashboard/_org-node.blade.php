<li>
    <article class="org-node-card">
        <div class="org-node-title">{{ $node['title'] ?: 'Untitled Designation' }}</div>
        @if(!empty($node['title_fa']))
            <div class="org-node-subtitle">{{ $node['title_fa'] }}</div>
        @endif

        <div class="org-people">
            @forelse($node['employees'] as $employee)
                <div class="org-person">
                    <span class="org-avatar">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                    <span>
                        <strong>{{ $employee->name }}</strong>
                        <small>{{ $employee->branch?->name ?? 'No branch' }}</small>
                    </span>
                </div>
            @empty
                <div class="org-vacant">Vacant position</div>
            @endforelse
        </div>
    </article>

    @if(!empty($node['children']))
        <ul>
            @foreach($node['children'] as $childNode)
                @include('dashboard._org-node', ['node' => $childNode])
            @endforeach
        </ul>
    @endif
</li>
