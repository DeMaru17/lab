{{-- resources/views/overtimes/approval/_approve_modal.blade.php --}}
<div class="modal fade" id="approveOvertimeModal" tabindex="-1" aria-labelledby="approveOvertimeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-success text-white"> {{-- Header hijau untuk approve --}}
          <h5 class="modal-title" id="approveOvertimeModalLabel">Konfirmasi Persetujuan Lembur</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        {{-- Form di dalam modal --}}
        <form id="approveOvertimeForm" method="POST" action=""> {{-- Action diisi oleh JS --}}
          @csrf
          @method('PATCH') {{-- Method untuk route approve Asisten & Manager --}}
          <div class="modal-body">
            <p>Anda akan menyetujui pengajuan lembur untuk karyawan: <strong id="approveOvertimeUserName">Nama Karyawan</strong>.</p>
            {{-- Pesan tambahan khusus untuk Manager (jika perlu) --}}
            <p id="approveOvertimeManagerNote" style="display: none;">
              Ini adalah persetujuan final. Pastikan data sudah benar.
            </p>
             {{-- Pesan tambahan khusus untuk Asisten (jika perlu) --}}
            <p id="approveOvertimeAsistenNote" style="display: none;">
              Pengajuan akan diteruskan ke Manager untuk persetujuan final.
            </p>
            {{-- Bisa tambahkan textarea opsional untuk catatan approval jika perlu --}}
            {{-- <div class="mb-3">
              <label for="approveOvertimeNotes" class="form-label">Catatan Persetujuan (Opsional)</label>
              <textarea class="form-control" id="approveOvertimeNotes" name="approval_notes" rows="3"></textarea>
            </div> --}}
            <p>Apakah Anda yakin ingin melanjutkan?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-success">Ya, Setujui</button>
          </div>
        </form>
      </div>
    </div>
  </div>
