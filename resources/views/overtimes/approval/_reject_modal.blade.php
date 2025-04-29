{{-- resources/views/overtimes/approval/_reject_modal.blade.php --}}
<div class="modal fade" id="rejectOvertimeModal" tabindex="-1" aria-labelledby="rejectOvertimeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectOvertimeModalLabel">Tolak Pengajuan Lembur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            {{-- Form di dalam modal --}}
            <form id="rejectOvertimeForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
                @csrf {{-- CSRF Token --}}
                {{-- Method POST sesuai route reject --}}
                <div class="modal-body">
                    <p>Anda akan menolak pengajuan lembur untuk karyawan: <strong id="rejectOvertimeUserName">Nama
                            Karyawan</strong>.</p>
                    <div class="mb-3">
                        <label for="rejectOvertimeNotes" class="form-label">Alasan Penolakan <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejectOvertimeNotes" name="notes" rows="4" required
                            placeholder="Masukkan alasan mengapa pengajuan ditolak..."></textarea>
                        <div class="invalid-feedback">Alasan penolakan wajib diisi.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>
