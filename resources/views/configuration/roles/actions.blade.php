<div class="d-flex justify-content-center gap-2">
    @if($row->name !== 'Super Admin')
    <button type="button" onclick="editRole({{ $row->id }})" class="btn btn-sm btn-primary">
        <i class="bx bx-edit"></i>
    </button>
    <button type="button" onclick="confirmDelete({{ $row->id }})" class="btn btn-sm btn-danger">
        <i class="bx bx-trash"></i>
    </button>
    @else
    <span class="badge bg-label-secondary">Protected</span>
    @endif
</div>
