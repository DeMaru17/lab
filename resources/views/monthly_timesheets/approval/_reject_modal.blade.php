{{-- resources/views/monthly_timesheets/approval/_reject_modal.blade.php --}}
<div class="modal fade" id="rejectTimesheetModal" tabindex="-1" aria-labelledby="rejectTimesheetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
             <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectTimesheetModalLabel">Tolak Timesheet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectTimesheetForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
                @csrf
                @method('PATCH') {{-- Sesuaikan method route --}}
                <div class="modal-body">
                    <p>Anda akan menolak timesheet untuk karyawan: <strong id="rejectTimesheetUserName">Nama Karyawan</strong>.</p>
                    <div class="mb-3">
                        <label for="rejectTimesheetNotes" class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectTimesheetNotes" name="notes" rows="4" required minlength="5" placeholder="Masukkan alasan mengapa timesheet ditolak..."></textarea>
                        <div class="invalid-feedback">Alasan penolakan wajib diisi (min. 5 karakter).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Timesheet</button>
                </div>
            </form>
        </div>
    </div>
</div>
