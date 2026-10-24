<div class="d-flex gap-2 justify-content-center">
    <button class="btn btn-sm btn-warning" onclick="editUser({{ $row->id }})">
        <i class="bi bi-pencil"></i>
    </button>
    <button class="btn btn-sm btn-danger" onclick="confirmDelete({{ $row->id }})">
        <i class="bi bi-trash"></i>
    </button>
</div>
