{{-- resources/views/monthly_timesheets/approval/_bulk_approve_confirm_modal.blade.php --}}
<div class="modal fade" id="bulkApproveConfirmModalTimesheet" tabindex="-1" aria-labelledby="bulkApproveConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="bulkApproveConfirmModalLabel">Konfirmasi Persetujuan Massal Timesheet</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda akan menyetujui <strong class="selected-count-display">0</strong> timesheet yang dipilih.</p>
                {{-- Teks ini akan diatur oleh JS berdasarkan halaman (Asisten/Manager) --}}
                <p id="bulk-approve-asisten-text" style="display: none;">Pengajuan akan diteruskan ke Manager.</p>
                <p id="bulk-approve-manager-text" style="display: none;">Ini adalah persetujuan final.</p>
                <p>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="confirmBulkApproveBtnTimesheet">Ya, Setujui yang Dipilih</button>
            </div>
        </div>
    </div>
</div>
