{{-- resources/views/overtimes/approval/_modal_scripts.blade.php --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --------------------------------------------------------------------
        // Inisialisasi Tooltip Bootstrap
        // --------------------------------------------------------------------
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // --------------------------------------------------------------------
        // Handling Modal Reject Lembur (Kode dari sebelumnya)
        // --------------------------------------------------------------------
        var rejectModal = document.getElementById('rejectOvertimeModal');
        var rejectForm = document.getElementById('rejectOvertimeForm');
        var rejectUserName = document.getElementById('rejectOvertimeUserName');
        var rejectNotes = document.getElementById('rejectOvertimeNotes');

        if (rejectModal && rejectForm && rejectUserName && rejectNotes) {
            rejectModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var overtimeId = button.getAttribute('data-overtime-id');
                var userName = button.getAttribute('data-user-name');
                rejectUserName.textContent = userName;
                var actionUrl = `{{ url('/overtime-approval') }}/${overtimeId}/reject`;
                rejectForm.action = actionUrl;
                rejectNotes.value = '';
                rejectNotes.classList.remove('is-invalid');
            });
            rejectForm.addEventListener('submit', function(event) {
                if (!rejectNotes.value || rejectNotes.value.trim() === '') {
                    rejectNotes.classList.add('is-invalid');
                    event.preventDefault();
                    rejectNotes.focus();
                } else {
                    rejectNotes.classList.remove('is-invalid');
                }
            });
        } else {
            console.warn('Satu atau lebih elemen untuk modal reject lembur tidak ditemukan.');
        }

        // --------------------------------------------------------------------
        // Handling Modal Konfirmasi Bulk Approve Lembur
        // --------------------------------------------------------------------
        var bulkApproveConfirmModal = document.getElementById('bulkApproveConfirmModal');
        var bulkApproveCountSpan = document.getElementById('bulkApproveCount');
        var confirmBulkApproveBtn = document.getElementById('confirmBulkApproveBtn');
        var bulkApproveAsistenNote = document.getElementById('bulkApproveAsistenNoteConfirm');
        var bulkApproveManagerNote = document.getElementById('bulkApproveManagerNoteConfirm');

        // Tentukan form utama mana yang aktif di halaman ini
        var mainBulkFormId = null;
        if (document.getElementById('bulk-approve-form-asisten')) {
            mainBulkFormId = 'bulk-approve-form-asisten';
        } else if (document.getElementById('bulk-approve-form-manager')) {
            mainBulkFormId = 'bulk-approve-form-manager';
        }

        if (bulkApproveConfirmModal && bulkApproveCountSpan && confirmBulkApproveBtn && mainBulkFormId) {
            bulkApproveConfirmModal.addEventListener('show.bs.modal', function(event) {
                // Ambil jumlah dari tombol trigger (atau hitung ulang)
                let selectedCount = 0;
                let level = 'unknown';
                if (mainBulkFormId === 'bulk-approve-form-asisten') {
                    selectedCount = parseInt(document.getElementById('selected-count-asisten')
                        .textContent, 10);
                    level = 'asisten';
                    if (bulkApproveAsistenNote) bulkApproveAsistenNote.style.display = 'block';
                    if (bulkApproveManagerNote) bulkApproveManagerNote.style.display = 'none';
                } else if (mainBulkFormId === 'bulk-approve-form-manager') {
                    selectedCount = parseInt(document.getElementById('selected-count-manager')
                        .textContent, 10);
                    level = 'manager';
                    if (bulkApproveAsistenNote) bulkApproveAsistenNote.style.display = 'none';
                    if (bulkApproveManagerNote) bulkApproveManagerNote.style.display = 'block';
                }

                // Update jumlah di modal
                bulkApproveCountSpan.textContent = selectedCount;

                // Pastikan tombol submit modal aktif hanya jika ada yg dipilih
                confirmBulkApproveBtn.disabled = selectedCount === 0;
            });

            // Event listener untuk tombol konfirmasi di dalam modal
            confirmBulkApproveBtn.addEventListener('click', function() {
                const mainForm = document.getElementById(mainBulkFormId);
                if (mainForm) {
                    // Submit form utama yang ada di halaman (yang berisi checkbox)
                    mainForm.submit();
                    // Optional: disable tombol modal untuk mencegah double submit
                    this.disabled = true;
                    this.textContent = 'Memproses...';
                    // Optional: tutup modal secara manual
                    // var modalInstance = bootstrap.Modal.getInstance(bulkApproveConfirmModal);
                    // modalInstance.hide();
                } else {
                    console.error('Form utama bulk approve tidak ditemukan:', mainBulkFormId);
                    alert('Terjadi kesalahan. Gagal menemukan form.');
                }
            });

        } else {
            if (!bulkApproveConfirmModal) console.warn(
            'Elemen modal #bulkApproveConfirmModal tidak ditemukan.');
            if (!bulkApproveCountSpan) console.warn('Elemen span #bulkApproveCount tidak ditemukan.');
            if (!confirmBulkApproveBtn) console.warn('Elemen tombol #confirmBulkApproveBtn tidak ditemukan.');
            if (!mainBulkFormId) console.warn(
                'Form utama bulk approve (asisten/manager) tidak ditemukan di halaman ini.');
        }

    });
</script>
