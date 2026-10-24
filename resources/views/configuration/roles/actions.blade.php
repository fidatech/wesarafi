<div class="d-flex justify-content-center gap-2">
    <button onclick="editRole({{ $row->id }})" class="btn btn-sm btn-primary">
        <i class="bx bx-edit"></i>
    </button>
    <button onclick="confirmDelete({{ $row->id }})" class="btn btn-sm btn-danger">
        <i class="bx bx-trash"></i>
    </button>
</div>
