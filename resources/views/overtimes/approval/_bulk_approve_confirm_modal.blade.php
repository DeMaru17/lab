{{-- resources/views/overtimes/approval/_bulk_approve_confirm_modal.blade.php --}}
<div class="modal fade" id="bulkApproveConfirmModal" tabindex="-1" aria-labelledby="bulkApproveConfirmModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="bulkApproveConfirmModalLabel">Konfirmasi Persetujuan Massal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda akan menyetujui <strong id="bulkApproveCount">0</strong> pengajuan lembur yang dipilih.</p>
                {{-- Tambahkan pesan sesuai level jika perlu --}}
                <p id="bulkApproveAsistenNoteConfirm" style="display: none;">Pengajuan akan diteruskan ke Manager untuk
                    persetujuan final.</p>
                <p id="bulkApproveManagerNoteConfirm" style="display: none;">Ini adalah persetujuan final.</p>
                <p>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                {{-- Tombol ini akan men-trigger submit form utama via JS --}}
                <button type="button" class="btn btn-success" id="confirmBulkApproveBtn">Ya, Setujui yang
                    Dipilih</button>
            </div>
        </div>
    </div>
</div>
